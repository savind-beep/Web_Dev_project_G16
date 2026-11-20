<?php
require_once "db.php";

requireAdmin();

$msg = trim($_GET['msg'] ?? '');
$error = trim($_GET['error'] ?? '');
$tab = trim($_POST['tab'] ?? $_GET['tab'] ?? 'dashboard');
$is_restricted_tab = ($tab === 'restricted');
if ($is_restricted_tab) {
    $tab = 'approvals';
}
$edit_id = intval($_GET['edit_id'] ?? $_POST['article_id'] ?? 0);
$appoint_id = intval($_GET['appoint_id'] ?? 0);

// POST & GET actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' || (!empty($_GET['action']) && in_array($_GET['action'], ['restrict_article', 'unrestrict_article', 'delete_mindscape', 'admin_delete_brick', 'delete_brick']))) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // 1. Add New Article
    if ($action === 'add_article') {
        $title = trim($_POST['title'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $author_id = $_SESSION['user_id'];
        $status = $_POST['status'] ?? 'approved';
        $image_url = handleArticleImageUpload('image_file', 'image_url');

        if (empty($title) || $category_id <= 0 || empty($content)) {
            $error = "Please fill in all required fields.";
            $tab = 'new';
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO articles (title, category_id, author_id, content, image_url, status) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "siisss", $title, $category_id, $author_id, $content, $image_url, $status);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header("Location: admin.php?tab=articles&msg=" . urlencode("Article published successfully!"));
                exit;
            } else {
                $error = "Failed to create article: " . mysqli_error($conn);
                $tab = 'new';
            }
        }
    }

    // 2. Edit Existing Article
    elseif ($action === 'edit_article') {
        $art_id = intval($_POST['article_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $status = $_POST['status'] ?? 'approved';
        $existing_img = trim($_POST['existing_image_url'] ?? '');
        $image_url = handleArticleImageUpload('image_file', 'image_url', $existing_img);

        if (empty($title) || $category_id <= 0 || empty($content)) {
            $error = "Please fill in all required fields.";
            $tab = 'edit';
            $edit_id = $art_id;
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE articles SET title = ?, category_id = ?, image_url = ?, content = ?, status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sisssi", $title, $category_id, $image_url, $content, $status, $art_id);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header("Location: admin.php?tab=articles&msg=" . urlencode("Article #$art_id updated successfully!"));
                exit;
            } else {
                $error = "Failed to update article: " . mysqli_error($conn);
                $tab = 'edit';
                $edit_id = $art_id;
            }
        }
    }

    // 3. Delete / Remove Article
    elseif ($action === 'delete_article') {
        $del_id = intval($_POST['article_id'] ?? 0);
        if ($del_id > 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM articles WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $del_id);
            if (mysqli_stmt_execute($stmt)) {
                $msg = "Article #$del_id successfully removed from database.";
            } else {
                $error = "Failed to remove article.";
            }
            mysqli_stmt_close($stmt);
        }
        $return_url = trim($_POST['return_url'] ?? '');
        if (!empty($return_url)) {
            $sep = (strpos($return_url, '?') !== false) ? '&' : '?';
            header("Location: " . $return_url . $sep . "msg=" . urlencode($msg ?? $error));
            exit;
        }
    }

    // 3b. Restrict Article (hide from public view)
    elseif ($action === 'restrict_article') {
        $art_id = intval($_POST['article_id'] ?? $_GET['article_id'] ?? 0);
        $restrict_msg = "";
        if ($art_id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE articles SET status = 'restricted' WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $art_id);
            if (mysqli_stmt_execute($stmt)) {
                $restrict_msg = "Article #$art_id has been restricted and hidden from public view.";
            } else {
                $restrict_msg = "Failed to restrict article: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }

        $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || 
                   (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == '1') ||
                   (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'status' => 'restricted',
                'article_id' => $art_id,
                'message' => $restrict_msg
            ]);
            exit;
        }

        $return_url = trim($_POST['return_url'] ?? $_GET['return_url'] ?? '');
        if (!empty($return_url)) {
            $sep = (strpos($return_url, '?') !== false) ? '&' : '?';
            header("Location: " . $return_url . $sep . "msg=" . urlencode($restrict_msg));
        } else {
            $redirect_tab = $_GET['tab'] ?? $_POST['tab'] ?? 'articles';
            header("Location: admin.php?tab=$redirect_tab&msg=" . urlencode($restrict_msg));
        }
        exit;
    }

    // 3c. Unrestrict Article (restore to active/published)
    elseif ($action === 'unrestrict_article') {
        $art_id = intval($_POST['article_id'] ?? $_GET['article_id'] ?? 0);
        $unrestrict_msg = "";
        if ($art_id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE articles SET status = 'approved' WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $art_id);
            if (mysqli_stmt_execute($stmt)) {
                $unrestrict_msg = "Article #$art_id restriction lifted. Now published live.";
            } else {
                $unrestrict_msg = "Failed to unrestrict article: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }

        $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || 
                   (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == '1') ||
                   (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'status' => 'approved',
                'article_id' => $art_id,
                'message' => $unrestrict_msg
            ]);
            exit;
        }

        $return_url = trim($_POST['return_url'] ?? $_GET['return_url'] ?? '');
        if (!empty($return_url)) {
            $sep = (strpos($return_url, '?') !== false) ? '&' : '?';
            header("Location: " . $return_url . $sep . "msg=" . urlencode($unrestrict_msg));
        } else {
            $redirect_tab = $_GET['tab'] ?? $_POST['tab'] ?? 'articles';
            header("Location: admin.php?tab=$redirect_tab&msg=" . urlencode($unrestrict_msg));
        }
        exit;
    }

    // 4. Update Article Status
    elseif ($action === 'update_status') {
        $art_id = intval($_POST['article_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';
        if ($art_id > 0 && in_array($new_status, ['approved', 'restricted', 'rejected', 'pending'])) {
            $stmt = mysqli_prepare($conn, "UPDATE articles SET status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $new_status, $art_id);
            if (mysqli_stmt_execute($stmt)) {
                $msg = "Article #$art_id status updated to " . strtoupper($new_status) . ".";
            }
            mysqli_stmt_close($stmt);
        }
    }

    // 5. Add Category
    elseif ($action === 'add_category') {
        $cat_name = trim($_POST['name'] ?? '');
        $cat_slug = trim($_POST['slug'] ?? '');
        $cat_desc = trim($_POST['description'] ?? '');

        if (empty($cat_slug)) {
            $cat_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $cat_name)));
        }

        if (empty($cat_name) || empty($cat_slug)) {
            $error = "Category Name and Slug are required.";
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sss", $cat_name, $cat_slug, $cat_desc);
            if (mysqli_stmt_execute($stmt)) {
                $msg = "Category '$cat_name' added successfully!";
            } else {
                $error = "Failed to add category. Name or slug might already exist.";
            }
            mysqli_stmt_close($stmt);
        }
        $tab = 'categories';
    }

    // 6. Delete Category
    elseif ($action === 'delete_category') {
        $del_cat_id = intval($_POST['category_id'] ?? 0);
        if ($del_cat_id > 0) {
            $check = mysqli_query($conn, "SELECT COUNT(*) AS total FROM articles WHERE category_id = $del_cat_id");
            $c_row = mysqli_fetch_assoc($check);
            if ($c_row['total'] > 0) {
                $error = "Cannot delete category: {$c_row['total']} article(s) are assigned to it.";
            } else {
                $stmt = mysqli_prepare($conn, "DELETE FROM categories WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $del_cat_id);
                if (mysqli_stmt_execute($stmt)) {
                    $msg = "Category successfully deleted.";
                }
                mysqli_stmt_close($stmt);
            }
        }
        $tab = 'categories';
    }

    // 7. Delete Comment
    elseif ($action === 'delete_comment') {
        $del_cm_id = intval($_POST['comment_id'] ?? 0);
        if ($del_cm_id > 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM comments WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $del_cm_id);
            if (mysqli_stmt_execute($stmt)) {
                $msg = "Comment #$del_cm_id removed.";
            }
            mysqli_stmt_close($stmt);
        }
        $tab = 'comments';
    }

    // 8. Delete / Drop User
    elseif ($action === 'delete_user') {
        $del_user_id = intval($_POST['user_id'] ?? 0);
        if ($del_user_id <= 0) {
            $error = "Invalid user ID.";
        } elseif ($del_user_id === intval($_SESSION['user_id'])) {
            $error = "You cannot delete your own active administrator account!";
        } else {
            $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $del_user_id);
            if (mysqli_stmt_execute($stmt)) {
                $msg = "User #$del_user_id and all associated articles/comments have been deleted.";
            } else {
                $error = "Failed to delete user: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
        $tab = 'users';
    }

    // 8b. Appoint User as Administrator
    elseif ($action === 'appoint_admin') {
        $target_user_id = intval($_POST['user_id'] ?? 0);
        if ($target_user_id <= 0) {
            $error = "Please select a valid user to appoint as administrator.";
        } else {
            $chk = mysqli_prepare($conn, "SELECT name, email, role FROM users WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, "i", $target_user_id);
            mysqli_stmt_execute($chk);
            $res = mysqli_stmt_get_result($chk);
            $target = mysqli_fetch_assoc($res);
            mysqli_stmt_close($chk);

            if (!$target) {
                $error = "User not found in database.";
            } elseif ($target['role'] === 'admin') {
                $error = "User '" . safe($target['name']) . "' is already an administrator.";
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE users SET role = 'admin', banned_until = NULL, ban_reason = NULL, is_reported = 0 WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $target_user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $msg = "Administrator appointed: '" . safe($target['name']) . "' (" . safe($target['email']) . ") now has full administrative privileges.";
                } else {
                    $error = "Failed to appoint administrator: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
        }
        if (!empty($error)) {
            header("Location: admin.php?tab=users&error=" . urlencode($error));
        } else {
            header("Location: admin.php?tab=users&msg=" . urlencode($msg));
        }
        exit;
    }

    // 8c. Demote / Revoke Administrator Access
    elseif ($action === 'demote_admin') {
        $target_user_id = intval($_POST['user_id'] ?? 0);
        if ($target_user_id <= 0) {
            $error = "Invalid user ID.";
        } elseif ($target_user_id === intval($_SESSION['user_id'])) {
            $error = "You cannot revoke your own administrator privileges!";
        } else {
            $chk = mysqli_prepare($conn, "SELECT name, email, role FROM users WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, "i", $target_user_id);
            mysqli_stmt_execute($chk);
            $res = mysqli_stmt_get_result($chk);
            $target = mysqli_fetch_assoc($res);
            mysqli_stmt_close($chk);

            if (!$target) {
                $error = "User not found in database.";
            } elseif ($target['role'] !== 'admin') {
                $error = "User '" . safe($target['name']) . "' is not an administrator.";
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE users SET role = 'student' WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $target_user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $msg = "Administrator privileges revoked for '" . safe($target['name']) . "'. Role changed back to Student.";
                } else {
                    $error = "Failed to revoke administrator privileges: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
        }
        if (!empty($error)) {
            header("Location: admin.php?tab=users&error=" . urlencode($error));
        } else {
            header("Location: admin.php?tab=users&msg=" . urlencode($msg));
        }
        exit;
    }

    // 9. Report / Flag User
    elseif ($action === 'report_user') {
        $rep_user_id = intval($_POST['user_id'] ?? 0);
        $reason = trim($_POST['report_reason'] ?? '');
        if (empty($reason)) {
            $reason = "Flagged by administrator for investigation or inappropriate conduct.";
        }
        if ($rep_user_id <= 0) {
            $error = "Invalid user ID.";
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE users SET is_reported = 1, report_reason = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $reason, $rep_user_id);
            if (mysqli_stmt_execute($stmt)) {
                $msg = "User #$rep_user_id has been reported and flagged.";
            } else {
                $error = "Failed to report user: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
        $tab = 'users';
    }

    // 10. Clear Report Flag
    elseif ($action === 'clear_report') {
        $rep_user_id = intval($_POST['user_id'] ?? 0);
        if ($rep_user_id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE users SET is_reported = 0, report_reason = NULL WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $rep_user_id);
            if (mysqli_stmt_execute($stmt)) {
                $msg = "Report flag on user #$rep_user_id has been cleared.";
            }
            mysqli_stmt_close($stmt);
        }
        $tab = 'users';
    }

    // 11. Ban User Profile (3 Hours, 1 Day, 5 Days)
    elseif ($action === 'ban_user') {
        $ban_user_id = intval($_POST['user_id'] ?? 0);
        $duration = $_POST['duration'] ?? '3_hours';
        $reason = trim($_POST['ban_reason'] ?? '');
        if (empty($reason)) {
            $reason = "Temporary suspension by administrator for policy violation.";
        }

        if ($ban_user_id <= 0) {
            $error = "Invalid user ID.";
        } elseif ($ban_user_id === intval($_SESSION['user_id'])) {
            $error = "You cannot suspend your own active administrator account!";
        } else {
            $interval = "+3 hours";
            $duration_label = "3 Hours";
            if ($duration === '1_day') {
                $interval = "+1 day";
                $duration_label = "1 Day (24 Hours)";
            } elseif ($duration === '5_days') {
                $interval = "+5 days";
                $duration_label = "5 Days";
            }
            $banned_until = date("Y-m-d H:i:s", strtotime($interval));

            $stmt = mysqli_prepare($conn, "UPDATE users SET banned_until = ?, ban_reason = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssi", $banned_until, $reason, $ban_user_id);
            if (mysqli_stmt_execute($stmt)) {
                $msg = "User #$ban_user_id profile has been banned for $duration_label (until " . date("M d, Y, g:i A", strtotime($banned_until)) . ").";
            } else {
                $error = "Failed to ban user: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
        $tab = 'users';
    }

    // 12. Lift Ban (Unban User)
    elseif ($action === 'unban_user') {
        $unban_id = intval($_POST['user_id'] ?? 0);
        if ($unban_id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE users SET banned_until = NULL, ban_reason = NULL WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $unban_id);
            if (mysqli_stmt_execute($stmt)) {
                $msg = "Ban lifted for user #$unban_id. Profile has been reinstated to active status.";
            } else {
                $error = "Failed to unban user: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
        $tab = 'users';
    }

    // 13. Delete Mindscape Thought Brick
    elseif ($action === 'delete_mindscape' || $action === 'admin_delete_brick' || $action === 'delete_brick') {
        $del_thought_id = intval($_POST['thought_id'] ?? $_GET['thought_id'] ?? $_POST['brick_id'] ?? $_GET['brick_id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);
        $ms_search_val = trim($_POST['ms_search'] ?? $_GET['ms_search'] ?? '');
        if ($del_thought_id > 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM mindscape_thoughts WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $del_thought_id);
                $res = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            } else {
                $res = mysqli_query($conn, "DELETE FROM mindscape_thoughts WHERE id = " . intval($del_thought_id));
            }
            if ($res) {
                $msg = "Mindscape thought brick #$del_thought_id successfully removed from wall.";
            } else {
                $error = "Failed to delete thought brick: " . mysqli_error($conn);
            }
        }
        $tab = 'mindscape';
        $redirect_url = "admin.php?tab=mindscape";
        if (!empty($ms_search_val)) {
            $redirect_url .= "&ms_search=" . urlencode($ms_search_val);
        }
        if (!empty($msg)) {
            $redirect_url .= "&msg=" . urlencode($msg);
        } elseif (!empty($error)) {
            $redirect_url .= "&error=" . urlencode($error);
        }
        header("Location: " . $redirect_url);
        exit;
    }
}

// ==========================================
// DATA QUERIES
// ==========================================
// 1. Statistics
$total_articles = 0;
$pending_articles = 0;
$total_users = 0;
$total_students = 0;
$total_admins = 0;
$total_reported = 0;
$total_comments = 0;

$r1 = mysqli_query($conn, "SELECT COUNT(*) AS c FROM articles WHERE status = 'approved'");
if ($row = mysqli_fetch_assoc($r1)) $total_articles = $row['c'];

$restricted_articles = 0;
$r_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM articles WHERE status = 'restricted'");
if ($row = mysqli_fetch_assoc($r_res)) $restricted_articles = $row['c'];

$r2 = mysqli_query($conn, "SELECT COUNT(*) AS c FROM articles WHERE status = 'pending'");
if ($row = mysqli_fetch_assoc($r2)) $pending_articles = $row['c'];

$r3_all = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users");
if ($row = mysqli_fetch_assoc($r3_all)) $total_users = $row['c'];

$r3_stu = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'student'");
if ($row = mysqli_fetch_assoc($r3_stu)) $total_students = $row['c'];

$r3_adm = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'admin'");
if ($row = mysqli_fetch_assoc($r3_adm)) $total_admins = $row['c'];

$r3_rep = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE is_reported = 1");
if ($row = mysqli_fetch_assoc($r3_rep)) $total_reported = $row['c'];

$r4 = mysqli_query($conn, "SELECT COUNT(*) AS c FROM comments");
if ($row = mysqli_fetch_assoc($r4)) $total_comments = $row['c'];

$total_banned = 0;
$r_ban = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE banned_until > NOW()");
if ($r_ban && $row = mysqli_fetch_assoc($r_ban)) $total_banned = $row['c'];

$total_thoughts = 0;
$r_th = mysqli_query($conn, "SELECT COUNT(*) AS c FROM mindscape_thoughts");
if ($r_th && $row = mysqli_fetch_assoc($r_th)) $total_thoughts = $row['c'];

// Data for User Management Tab
$u_search = trim($_GET['u_search'] ?? '');
$u_role = trim($_GET['u_role'] ?? '');
$u_reported = trim($_GET['u_reported'] ?? '');
$u_banned = trim($_GET['u_banned'] ?? '');

$u_sql = "SELECT u.*, 
          (SELECT COUNT(*) FROM articles a WHERE a.author_id = u.id) AS article_count,
          (SELECT COUNT(*) FROM comments cm WHERE cm.user_id = u.id) AS comment_count,
          (SELECT COUNT(*) FROM mindscape_thoughts mt WHERE mt.user_id = u.id) AS mindscape_count
          FROM users u
          WHERE 1=1";
$u_params = [];
$u_types = "";

if (!empty($u_role)) {
    $u_sql .= " AND u.role = ?";
    $u_params[] = $u_role;
    $u_types .= "s";
}
if ($u_reported === '1') {
    $u_sql .= " AND u.is_reported = 1";
}
if ($u_banned === '1') {
    $u_sql .= " AND u.banned_until > NOW()";
}
if (!empty($u_search)) {
    $u_sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.student_id LIKE ?)";
    $u_like = "%" . $u_search . "%";
    $u_params[] = $u_like;
    $u_params[] = $u_like;
    $u_params[] = $u_like;
    $u_types .= "sss";
}
$u_sql .= " ORDER BY u.created_at DESC";
$u_stmt = mysqli_prepare($conn, $u_sql);
if (!empty($u_params)) {
    mysqli_stmt_bind_param($u_stmt, $u_types, ...$u_params);
}
mysqli_stmt_execute($u_stmt);
$users_list = mysqli_stmt_get_result($u_stmt);

// Data for Mindscape Moderation Tab
$ms_search = trim($_GET['ms_search'] ?? '');
$ms_sql = "SELECT mt.*, u.email AS user_email 
           FROM mindscape_thoughts mt 
           LEFT JOIN users u ON mt.user_id = u.id";
if (!empty($ms_search)) {
    $ms_sql .= " WHERE mt.thought LIKE ? OR mt.author_name LIKE ? OR mt.student_id LIKE ?";
}
$ms_sql .= " ORDER BY mt.created_at DESC";
$ms_stmt = mysqli_prepare($conn, $ms_sql);
if (!empty($ms_search)) {
    $ms_like = "%" . $ms_search . "%";
    mysqli_stmt_bind_param($ms_stmt, "sss", $ms_like, $ms_like, $ms_like);
}
mysqli_stmt_execute($ms_stmt);
$mindscape_list = mysqli_stmt_get_result($ms_stmt);

// Categories dropdown list (for forms)
$cat_options = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");

// Data for Edit Tab
$edit_article = null;
if ($edit_id > 0) {
    $e_stmt = mysqli_prepare($conn, "SELECT * FROM articles WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($e_stmt, "i", $edit_id);
    mysqli_stmt_execute($e_stmt);
    $edit_article = mysqli_fetch_assoc(mysqli_stmt_get_result($e_stmt));
    mysqli_stmt_close($e_stmt);
    if ($edit_article) {
        $tab = 'edit';
    }
}

// Data for Articles Tab
$filter_cat = intval($_GET['category'] ?? 0);
$filter_status = trim($_GET['status'] ?? '');
$search_term = trim($_GET['search'] ?? '');

$art_sql = "SELECT a.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name,
            (SELECT COUNT(*) FROM comments cm WHERE cm.article_id = a.id) AS comment_count
            FROM articles a
            JOIN categories c ON a.category_id = c.id
            JOIN users u ON a.author_id = u.id
            WHERE 1=1";
$params = [];
$types = "";

if (!empty($filter_status)) {
    $art_sql .= " AND a.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if ($filter_cat > 0) {
    $art_sql .= " AND a.category_id = ?";
    $params[] = $filter_cat;
    $types .= "i";
}
if (!empty($search_term)) {
    $art_sql .= " AND (a.title LIKE ? OR u.name LIKE ?)";
    $like = "%" . $search_term . "%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}
$art_sql .= " ORDER BY a.created_at DESC";
$art_stmt = mysqli_prepare($conn, $art_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($art_stmt, $types, ...$params);
}
mysqli_stmt_execute($art_stmt);
$articles_list = mysqli_stmt_get_result($art_stmt);

// Data for Moderation / Restricted Tab
$pending_submissions = mysqli_query($conn, "SELECT a.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name, u.email AS author_email, u.student_id
                                            FROM articles a
                                            JOIN categories c ON a.category_id = c.id
                                            JOIN users u ON a.author_id = u.id
                                            WHERE a.status = 'restricted' OR a.status = 'pending'
                                            ORDER BY a.updated_at DESC");

// Data for Categories Tab
$categories_list = mysqli_query($conn, "SELECT c.*, COUNT(a.id) AS article_count 
                                       FROM categories c 
                                       LEFT JOIN articles a ON c.id = a.category_id 
                                       GROUP BY c.id ORDER BY c.name ASC");

// Data for Comments Tab
$cm_search = trim($_GET['cm_search'] ?? '');
$cm_sql = "SELECT c.*, a.title AS article_title, u.name AS user_name, u.email AS user_email, u.role AS user_role
           FROM comments c
           JOIN articles a ON c.article_id = a.id
           JOIN users u ON c.user_id = u.id";
if (!empty($cm_search)) {
    $cm_sql .= " WHERE c.comment_text LIKE ? OR u.name LIKE ? OR a.title LIKE ?";
}
$cm_sql .= " ORDER BY c.created_at DESC";
$cm_stmt = mysqli_prepare($conn, $cm_sql);
if (!empty($cm_search)) {
    $c_like = "%" . $cm_search . "%";
    mysqli_stmt_bind_param($cm_stmt, "sss", $c_like, $c_like, $c_like);
}
mysqli_stmt_execute($cm_stmt);
$comments_list = mysqli_stmt_get_result($cm_stmt);

$pageTitle = "Admin Control Panel";
include __DIR__ . "/includes/header.php";
?>

<div class="container py-4">
    <!-- Admin Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-shield-lock-fill text-success me-2"></i>Admin Dashboard</h2>
            <p class="text-muted small mb-0">Welcome, <strong><?php echo safe($_SESSION['user_name']); ?></strong>. Overview of articles, approvals, and moderation.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="admin.php?tab=new" class="btn btn-nsbm btn-sm">
                <i class="bi bi-plus-lg me-1"></i> New Article
            </a>
            <a href="index.php" class="btn btn-outline-secondary btn-sm" target="_blank">
                <i class="bi bi-box-arrow-up-right me-1"></i> Live Site
            </a>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (!empty($msg)): ?>
        <div class="alert alert-success alert-dismissible fade show alert-auto-dismiss" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo safe($msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo safe($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills mb-4 bg-white shadow-sm p-1 rounded-3 border gap-1">
        <li class="nav-item">
            <a class="nav-link py-2 px-3 <?php echo ($tab === 'dashboard') ? 'active bg-success' : 'text-secondary'; ?>" href="admin.php?tab=dashboard">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-2 px-3 <?php echo ($tab === 'articles') ? 'active bg-success' : 'text-secondary'; ?>" href="admin.php?tab=articles">
                <i class="bi bi-journals me-1"></i> Articles <span class="badge <?php echo ($tab === 'articles') ? 'bg-white text-success' : 'bg-light text-secondary border'; ?> rounded-pill ms-1"><?php echo $total_articles; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-2 px-3 position-relative <?php echo ($tab === 'approvals' || $tab === 'restricted') ? 'active bg-success' : 'text-secondary'; ?>" href="admin.php?tab=restricted">
                <i class="bi bi-slash-circle me-1"></i> Restricted
                <?php if ($restricted_articles > 0): ?>
                    <span class="badge bg-warning text-dark rounded-pill ms-1"><?php echo $restricted_articles; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-2 px-3 <?php echo ($tab === 'categories') ? 'active bg-success' : 'text-secondary'; ?>" href="admin.php?tab=categories">
                <i class="bi bi-tags me-1"></i> Categories
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-2 px-3 <?php echo ($tab === 'comments') ? 'active bg-success' : 'text-secondary'; ?>" href="admin.php?tab=comments">
                <i class="bi bi-chat-left-dots me-1"></i> Comments
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-2 px-3 <?php echo ($tab === 'users') ? 'active bg-success' : 'text-secondary'; ?>" href="admin.php?tab=users">
                <i class="bi bi-people me-1"></i> Users
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-2 px-3 <?php echo ($tab === 'mindscape') ? 'active bg-success' : 'text-secondary'; ?>" href="admin.php?tab=mindscape">
                <i class="bi bi-bricks me-1"></i> Mindscape
            </a>
        </li>
        <li class="nav-item ms-auto">
            <a class="nav-link py-2 px-3 <?php echo ($tab === 'new') ? 'active bg-success' : 'text-success fw-semibold'; ?>" href="admin.php?tab=new">
                <i class="bi bi-pencil-square me-1"></i> Write Story
            </a>
        </li>
    </ul>

    <!-- Tab 1: Dashboard -->
    <?php if ($tab === 'dashboard'): ?>
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg">
                <a href="admin.php?tab=articles" class="stat-card text-decoration-none d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Live Articles</span>
                        <div class="stat-val text-success"><?php echo $total_articles; ?></div>
                    </div>
                    <div class="stat-icon bg-success-subtle text-success">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg">
                <a href="admin.php?tab=restricted" class="stat-card text-decoration-none d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Restricted Stories</span>
                        <div class="stat-val text-warning"><?php echo $restricted_articles; ?></div>
                    </div>
                    <div class="stat-icon bg-warning-subtle text-warning">
                        <i class="bi bi-slash-circle"></i>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg">
                <a href="admin.php?tab=users" class="stat-card text-decoration-none d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Accounts</span>
                        <div class="stat-val text-primary">
                            <?php echo $total_users; ?>
                            <?php if ($total_banned > 0): ?>
                                <span class="badge bg-danger rounded-pill text-xs ms-1" style="font-size: 0.65rem;"><?php echo $total_banned; ?> Banned</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="stat-icon bg-primary-subtle text-primary">
                        <i class="bi bi-people"></i>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg">
                <a href="admin.php?tab=mindscape" class="stat-card text-decoration-none d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Mindscape Bricks</span>
                        <div class="stat-val text-dark"><?php echo $total_thoughts; ?></div>
                    </div>
                    <div class="stat-icon bg-success-subtle text-success">
                        <i class="bi bi-bricks"></i>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg">
                <a href="admin.php?tab=comments" class="stat-card text-decoration-none d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Comments</span>
                        <div class="stat-val text-info"><?php echo $total_comments; ?></div>
                    </div>
                    <div class="stat-icon bg-info-subtle text-info">
                        <i class="bi bi-chat-left-text"></i>
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0"><i class="bi bi-slash-circle text-warning me-2"></i>Restricted Stories Pending Review</h5>
                        <a href="admin.php?tab=restricted" class="btn btn-sm btn-outline-secondary">Go to Review Queue</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th class="ps-3">Headline</th>
                                        <th>Author</th>
                                        <th>Category</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($pending_submissions && mysqli_num_rows($pending_submissions) > 0): ?>
                                        <?php 
                                        mysqli_data_seek($pending_submissions, 0);
                                        $cnt = 0;
                                        while ($p = mysqli_fetch_assoc($pending_submissions)): 
                                            if (++$cnt > 5) break;
                                        ?>
                                            <tr>
                                                <td class="ps-3">
                                                    <div class="fw-semibold text-truncate mw-250"><?php echo safe($p['title']); ?></div>
                                                    <div class="text-muted text-xs"><?php echo date("M d, Y", strtotime($p['created_at'])); ?></div>
                                                </td>
                                                <td class="small"><?php echo safe($p['author_name']); ?></td>
                                                <td><span class="badge bg-light text-dark border"><?php echo safe($p['category_name']); ?></span></td>
                                                <td class="text-end pe-3">
                                                    <a href="admin.php?tab=restricted" class="btn btn-sm btn-nsbm">Review</a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted small">
                                                <i class="bi bi-check2-all text-success fs-3 d-block mb-1"></i>
                                                All stories are published live! No restricted stories.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0"><i class="bi bi-lightning-charge text-success me-2"></i>Quick Short Cuts</h5>
                    </div>
                    <div class="card-body p-3">
                        <div class="d-grid gap-2">
                            <a href="admin.php?tab=new" class="btn btn-outline-success text-start py-2">
                                <i class="bi bi-plus-circle me-2"></i> Publish an Official University Notice
                            </a>
                            <a href="admin.php?tab=restricted" class="btn btn-outline-warning text-start py-2 text-dark">
                                <i class="bi bi-slash-circle me-2"></i> Review Restricted Stories (<?php echo $restricted_articles; ?>)
                            </a>
                            <a href="admin.php?tab=categories" class="btn btn-outline-primary text-start py-2">
                                <i class="bi bi-tag me-2"></i> Manage News Categories &amp; Faculties
                            </a>
                            <a href="admin.php?tab=comments" class="btn btn-outline-secondary text-start py-2">
                                <i class="bi bi-chat-quote me-2"></i> Moderate Student Discussions
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Tab 2: Articles -->
    <?php elseif ($tab === 'articles'): ?>
        <!-- Filter Card -->
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-light">
            <div class="card-body p-3">
                <form method="GET" action="admin.php" class="row g-2 align-items-center">
                    <input type="hidden" name="tab" value="articles">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search title or author..." value="<?php echo safe($search_term); ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="category" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                            <?php 
                            mysqli_data_seek($cat_options, 0);
                            while ($c = mysqli_fetch_assoc($cat_options)): 
                            ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($filter_cat == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo safe($c['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="approved" <?php echo ($filter_status === 'approved') ? 'selected' : ''; ?>>Active / Published</option>
                            <option value="restricted" <?php echo ($filter_status === 'restricted') ? 'selected' : ''; ?>>Restricted (Hidden)</option>
                            <option value="pending" <?php echo ($filter_status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-nsbm btn-sm w-100">Filter</button>
                        <a href="admin.php?tab=articles" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Articles Table -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-3 col-id">#</th>
                                <th>Headline</th>
                                <th>Category</th>
                                <th>Author</th>
                                <th>Stats</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($articles_list && mysqli_num_rows($articles_list) > 0): ?>
                                <?php while ($art = mysqli_fetch_assoc($articles_list)): ?>
                                    <tr>
                                        <td class="ps-3 text-muted small"><?php echo $art['id']; ?></td>
                                        <td>
                                            <a href="view_article.php?id=<?php echo $art['id']; ?>" class="fw-semibold text-dark text-decoration-none" target="_blank">
                                                <?php echo safe($art['title']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge badge-category badge-<?php echo safe($art['category_slug']); ?>">
                                                <?php echo safe($art['category_name']); ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted"><?php echo safe($art['author_name']); ?></td>
                                        <td class="small text-muted">
                                            <i class="bi bi-eye"></i> <?php echo $art['views']; ?> | <i class="bi bi-chat-text"></i> <?php echo $art['comment_count']; ?>
                                        </td>
                                        <td id="article_status_cell_<?php echo $art['id']; ?>">
                                            <?php if ($art['status'] === 'approved'): ?>
                                                <span class="badge badge-approved"><i class="bi bi-check-circle-fill me-1"></i>Active</span>
                                            <?php elseif ($art['status'] === 'restricted'): ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle" title="Restricted by admin"><i class="bi bi-slash-circle me-1"></i>Restricted</span>
                                            <?php else: ?>
                                                <span class="badge badge-pending"><?php echo ucfirst($art['status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted"><?php echo date("M d, Y", strtotime($art['created_at'])); ?></td>
                                        <td class="text-end pe-3">
                                            <div class="d-inline-flex gap-1 align-items-center">
                                                <a href="edit_article.php?id=<?php echo $art['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit Article">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <div id="restrict_btn_box_<?php echo $art['id']; ?>" class="d-inline">
                                                    <?php if ($art['status'] !== 'restricted'): ?>
                                                        <form method="POST" action="admin.php?tab=articles" class="m-0 d-inline form-restrict-toggle" data-id="<?php echo $art['id']; ?>" data-action="restrict">
                                                            <input type="hidden" name="action" value="restrict_article">
                                                            <input type="hidden" name="article_id" value="<?php echo $art['id']; ?>">
                                                            <button type="submit" id="btn_restrict_<?php echo $art['id']; ?>" class="btn btn-sm btn-outline-warning" title="Restrict Article (Hide from public)">
                                                                <i class="bi bi-slash-circle me-1"></i> Restrict
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" action="admin.php?tab=articles" class="m-0 d-inline form-restrict-toggle" data-id="<?php echo $art['id']; ?>" data-action="lift">
                                                            <input type="hidden" name="action" value="unrestrict_article">
                                                            <input type="hidden" name="article_id" value="<?php echo $art['id']; ?>">
                                                            <button type="submit" id="btn_unrestrict_<?php echo $art['id']; ?>" class="btn btn-sm btn-outline-success" title="Lift Restriction (Publish Live)">
                                                                <i class="bi bi-check-circle me-1"></i> Lift
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                                <form method="POST" action="admin.php?tab=articles" class="m-0 d-inline">
                                                    <input type="hidden" name="action" value="delete_article">
                                                    <input type="hidden" name="article_id" value="<?php echo $art['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger confirm-delete" title="Remove Article" data-message="Permanently remove this article?">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">No articles found matching filters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- Tab 3: Moderation / Restricted Articles -->
    <?php elseif ($tab === 'approvals' || $tab === 'restricted'): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-slash-circle text-warning me-2"></i>Restricted Stories Moderation</h5>
                <p class="text-muted small mb-0">Student stories publish live automatically. Articles shown here have been restricted and hidden from public feeds.</p>
            </div>
            <a href="admin.php?tab=articles" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-journals me-1"></i> All Articles
            </a>
        </div>

        <?php if ($pending_submissions && mysqli_num_rows($pending_submissions) > 0): ?>
            <?php 
            mysqli_data_seek($pending_submissions, 0);
            while ($sub = mysqli_fetch_assoc($pending_submissions)): 
            ?>
                <div class="card border-0 shadow-sm rounded-3 mb-4 border-start border-warning border-4">
                    <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge badge-category badge-<?php echo safe($sub['category_slug']); ?>">
                                <?php echo safe($sub['category_name']); ?>
                            </span>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                <i class="bi bi-slash-circle me-1"></i> Restricted
                            </span>
                            <span class="text-muted small">Updated <?php echo date("F d, Y, g:i A", strtotime($sub['updated_at'])); ?></span>
                        </div>
                        <div class="d-flex gap-2">
                            <!-- Lift Restriction / Publish -->
                            <form method="POST" action="admin.php?tab=restricted" class="m-0">
                                <input type="hidden" name="action" value="unrestrict_article">
                                <input type="hidden" name="article_id" value="<?php echo $sub['id']; ?>">
                                <button type="submit" class="btn btn-success btn-sm px-3" title="Restore article to live public view">
                                    <i class="bi bi-check-circle me-1"></i> Lift Restriction
                                </button>
                            </form>
                            <!-- Remove Article -->
                            <form method="POST" action="admin.php?tab=restricted" class="m-0">
                                <input type="hidden" name="action" value="delete_article">
                                <input type="hidden" name="article_id" value="<?php echo $sub['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm px-3 confirm-delete" data-message="Permanently remove this article from the system?">
                                    <i class="bi bi-trash me-1"></i> Remove Article
                                </button>
                            </form>
                            <a href="view_article.php?id=<?php echo $sub['id']; ?>" class="btn btn-outline-secondary btn-sm" title="Preview Article" target="_blank">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                            <a href="edit_article.php?id=<?php echo $sub['id']; ?>" class="btn btn-outline-secondary btn-sm" title="Edit Article">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-2"><?php echo safe($sub['title']); ?></h4>
                        <div class="small text-muted mb-3">
                            By <strong><?php echo safe($sub['author_name']); ?></strong> (Student ID: <code><?php echo safe($sub['student_id'] ?? 'N/A'); ?></code> &bull; Email: <?php echo safe($sub['author_email']); ?>)
                        </div>
                        <div class="p-3 bg-light rounded text-secondary small lh-lg submission-preview">
                            <?php echo nl2br(safe($sub['content'])); ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-3 p-5 text-center">
                <div class="text-success mb-2"><i class="bi bi-shield-check display-3"></i></div>
                <h5 class="fw-bold">No Restricted Articles</h5>
                <p class="text-muted small mb-0">Student articles publish automatically upon submission. If moderation is ever needed, click "Restrict" on any article to hide it from public view.</p>
            </div>
        <?php endif; ?>

    <!-- Tab 4: Categories -->
    <?php elseif ($tab === 'categories'): ?>
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-plus-circle text-success me-2"></i>Add New Category</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="admin.php?tab=categories">
                            <input type="hidden" name="action" value="add_category">
                            <div class="mb-3">
                                <label for="name" class="form-label fw-semibold small">Category Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" placeholder="e.g. Campus Societies" required>
                            </div>
                            <div class="mb-3">
                                <label for="slug" class="form-label fw-semibold small">URL Slug <span class="text-muted">(Optional)</span></label>
                                <input type="text" class="form-control" id="slug" name="slug" placeholder="e.g. campus-societies">
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label fw-semibold small">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            <button type="submit" class="btn btn-nsbm w-100"><i class="bi bi-check2 me-1"></i> Add Category</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-list-ul text-secondary me-2"></i>Current Categories</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th class="ps-3">Category</th>
                                        <th>Slug</th>
                                        <th>Articles</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($categories_list && mysqli_num_rows($categories_list) > 0): ?>
                                        <?php while ($cat = mysqli_fetch_assoc($categories_list)): ?>
                                            <tr>
                                                <td class="ps-3 fw-semibold">
                                                    <span class="badge badge-category badge-<?php echo safe($cat['slug']); ?>">
                                                        <?php echo safe($cat['name']); ?>
                                                    </span>
                                                </td>
                                                <td><code><?php echo safe($cat['slug']); ?></code></td>
                                                <td><span class="badge bg-light text-dark border"><?php echo $cat['article_count']; ?> articles</span></td>
                                                <td class="text-end pe-3">
                                                    <form method="POST" action="admin.php?tab=categories" class="m-0 d-inline">
                                                        <input type="hidden" name="action" value="delete_category">
                                                        <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger confirm-delete" title="Delete Category" data-message="Delete category '<?php echo safe($cat['name']); ?>'?">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Tab 5: Comments -->
    <?php elseif ($tab === 'comments'): ?>
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-light">
            <div class="card-body p-3">
                <form method="GET" action="admin.php" class="row g-2 align-items-center">
                    <input type="hidden" name="tab" value="comments">
                    <div class="col-md-10">
                        <input type="text" name="cm_search" class="form-control form-control-sm" placeholder="Search comments by keyword, student name, or article title..." value="<?php echo safe($cm_search); ?>">
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-nsbm btn-sm w-100">Search</button>
                        <a href="admin.php?tab=comments" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-3 col-id">#</th>
                                <th>Article</th>
                                <th>Commenter</th>
                                <th>Comment</th>
                                <th>Date</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($comments_list && mysqli_num_rows($comments_list) > 0): ?>
                                <?php while ($cm = mysqli_fetch_assoc($comments_list)): ?>
                                    <tr>
                                        <td class="ps-3 text-muted small"><?php echo $cm['id']; ?></td>
                                        <td>
                                            <a href="view_article.php?id=<?php echo $cm['article_id']; ?>" target="_blank" class="fw-semibold text-dark text-decoration-none small">
                                                <?php echo safe($cm['article_title']); ?> <i class="bi bi-box-arrow-up-right icon-xs"></i>
                                            </a>
                                        </td>
                                        <td class="small">
                                            <strong><?php echo safe($cm['user_name']); ?></strong>
                                            <div class="text-muted text-xs"><?php echo safe($cm['user_email']); ?></div>
                                        </td>
                                        <td class="small text-secondary mw-320"><?php echo safe($cm['comment_text']); ?></td>
                                        <td class="text-muted small"><?php echo date("M d, Y, g:i A", strtotime($cm['created_at'])); ?></td>
                                        <td class="text-end pe-3">
                                            <form method="POST" action="admin.php?tab=comments" class="m-0 d-inline">
                                                <input type="hidden" name="action" value="delete_comment">
                                                <input type="hidden" name="comment_id" value="<?php echo $cm['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger confirm-delete" title="Delete Comment" data-message="Delete this comment?">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">No comments found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- Tab 6: Users -->
    <?php elseif ($tab === 'users'): ?>
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-light">
            <div class="card-body p-3">
                <form method="GET" action="admin.php" class="row g-2 align-items-center">
                    <input type="hidden" name="tab" value="users">
                    <div class="col-md-5">
                        <input type="text" name="u_search" class="form-control form-control-sm" placeholder="Search by name, student ID, or email..." value="<?php echo safe($u_search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="u_role" class="form-select form-select-sm">
                            <option value="">All Account Roles</option>
                            <option value="student" <?php echo ($u_role === 'student') ? 'selected' : ''; ?>>Students Only</option>
                            <option value="admin" <?php echo ($u_role === 'admin') ? 'selected' : ''; ?>>Admins Only</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="u_banned" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="1" <?php echo ($u_banned === '1') ? 'selected' : ''; ?>>Banned Only</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-nsbm btn-sm w-100"><i class="bi bi-filter me-1"></i> Filter</button>
                        <a href="admin.php?tab=users" class="btn btn-outline-secondary btn-sm" title="Clear Filters"><i class="bi bi-x-lg"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-people text-success me-2"></i>Registered User Accounts</h6>
                    <div class="text-muted text-xs mt-1">Manage accounts, moderate misconduct, and appoint co-administrators.</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-secondary border"><?php echo mysqli_num_rows($users_list); ?> accounts found</span>
                    <button type="button" class="btn btn-nsbm btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#appointAdminModal" id="btnOpenAppointAdminModal">
                        <i class="bi bi-shield-plus me-1"></i> Appoint Admin
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-3 col-id">#</th>
                                <th>Student / User</th>
                                <th>Identification</th>
                                <th>Faculty</th>
                                <th>Activity</th>
                                <th>Status</th>
                                <th class="text-end pe-3" style="min-width: 280px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users_list && mysqli_num_rows($users_list) > 0): ?>
                                <?php while ($u = mysqli_fetch_assoc($users_list)): 
                                    $is_self = ($u['id'] == $_SESSION['user_id']);
                                    $is_banned = (!empty($u['banned_until']) && strtotime($u['banned_until']) > time());
                                    $u_words = explode(" ", trim($u['name']));
                                    $u_initials = strtoupper(substr($u_words[0] ?? 'U', 0, 1) . substr($u_words[1] ?? '', 0, 1));
                                ?>
                                    <tr class="<?php echo $is_banned ? 'table-danger bg-opacity-10' : ''; ?>">
                                        <td class="ps-3 text-muted small"><?php echo $u['id']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <img src="<?php echo safe(getAvatarUrl($u['avatar'] ?? 'ironman')); ?>" alt="Avatar" class="rounded-circle shadow-sm flex-shrink-0" style="width: 36px; height: 36px; object-fit: cover; border: 1.5px solid #16a34a;">
                                                <div>
                                                    <div class="fw-semibold text-dark">
                                                        <?php echo safe($u['name']); ?>
                                                        <?php if ($is_self): ?>
                                                            <span class="badge bg-secondary-subtle text-secondary small ms-1">You</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-muted text-xs">Joined <?php echo date("M Y", strtotime($u['created_at'])); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?php echo safe($u['email']); ?></div>
                                            <?php if (!empty($u['student_id'])): ?>
                                                <span class="badge bg-light text-dark border font-monospace text-xs"><?php echo safe($u['student_id']); ?></span>
                                            <?php endif; ?>
                                            <span class="badge <?php echo ($u['role'] === 'admin') ? 'bg-success text-white' : 'bg-info-subtle text-info border border-info-subtle'; ?> text-xs text-uppercase">
                                                <i class="bi <?php echo ($u['role'] === 'admin') ? 'bi-shield-fill-check' : 'bi-person'; ?> me-1"></i><?php echo safe($u['role']); ?>
                                            </span>
                                        </td>
                                        <td class="small text-secondary">
                                            <?php echo !empty($u['faculty']) ? safe($u['faculty']) : '<span class="text-muted fst-italic">Not specified</span>'; ?>
                                        </td>
                                        <td class="small">
                                            <span class="badge bg-light text-dark border me-1" title="Articles Published"><i class="bi bi-journal-text me-1 text-success"></i><?php echo $u['article_count']; ?></span>
                                            <span class="badge bg-light text-dark border me-1" title="Comments Posted"><i class="bi bi-chat-dots me-1 text-primary"></i><?php echo $u['comment_count']; ?></span>
                                            <span class="badge bg-light text-dark border" title="Mindscape Bricks"><i class="bi bi-bricks me-1 text-warning"></i><?php echo $u['mindscape_count']; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($is_banned): ?>
                                                <span class="badge bg-danger mb-1"><i class="bi bi-slash-circle me-1"></i> Banned</span>
                                                <div class="text-danger small text-xs fw-semibold">
                                                    Until: <?php echo date("M d, Y g:i A", strtotime($u['banned_until'])); ?>
                                                </div>
                                                <?php if (!empty($u['ban_reason'])): ?>
                                                    <div class="text-muted text-xs mw-250 text-truncate" title="<?php echo safe($u['ban_reason']); ?>">
                                                        Reason: <?php echo safe($u['ban_reason']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php elseif (!empty($u['is_reported']) && $u['is_reported'] == 1): ?>
                                                <span class="badge bg-warning text-dark"><i class="bi bi-flag-fill me-1"></i> Reported</span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i> Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <!-- View Profile Link -->
                                                <a href="profile.php?id=<?php echo $u['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="View Profile" id="viewProfileBtn_<?php echo $u['id']; ?>">
                                                    <i class="bi bi-box-arrow-up-right"></i>
                                                </a>

                                                <!-- Admin Appointment / Revoke Actions -->
                                                <?php if ($u['role'] === 'student'): ?>
                                                    <!-- Appoint to Admin Button -->
                                                    <form method="POST" action="admin.php?tab=users" class="m-0 d-inline">
                                                        <input type="hidden" name="action" value="appoint_admin">
                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success" id="appointAdminBtn_<?php echo $u['id']; ?>" title="Appoint <?php echo safe($u['name']); ?> as Administrator" data-confirm="Promote <?php echo safe($u['name']); ?> to Administrator role? They will receive full administrative access.">
                                                            <i class="bi bi-shield-plus me-1"></i> Make Admin
                                                        </button>
                                                    </form>
                                                <?php elseif (!$is_self): ?>
                                                    <!-- Revoke Admin Access -->
                                                    <form method="POST" action="admin.php?tab=users" class="m-0 d-inline">
                                                        <input type="hidden" name="action" value="demote_admin">
                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary text-danger" id="demoteAdminBtn_<?php echo $u['id']; ?>" title="Revoke Admin Access" data-confirm="Revoke administrator privileges from <?php echo safe($u['name']); ?>? Role will be changed back to Student.">
                                                            <i class="bi bi-shield-minus me-1"></i> Revoke
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- Ban / Unban Options -->
                                                <?php if (!$is_self): ?>
                                                    <?php if ($is_banned): ?>
                                                        <!-- Unban Form -->
                                                        <form method="POST" action="admin.php?tab=users" class="m-0 d-inline">
                                                            <input type="hidden" name="action" value="unban_user">
                                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-success" title="Lift Ban Immediately" id="unbanBtn_<?php echo $u['id']; ?>">
                                                                <i class="bi bi-unlock-fill me-1"></i> Unban
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <!-- Ban Dropdown -->
                                                        <div class="dropdown d-inline">
                                                            <button class="btn btn-sm btn-outline-warning dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Ban Student Profile" id="banDropdownBtn_<?php echo $u['id']; ?>">
                                                                <i class="bi bi-slash-circle me-1"></i> Ban
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border p-2" style="min-width: 210px;">
                                                                <li class="dropdown-header small fw-bold text-uppercase text-muted">Ban Student Profile</li>
                                                                <li>
                                                                    <form method="POST" action="admin.php?tab=users" class="m-0">
                                                                        <input type="hidden" name="action" value="ban_user">
                                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                                        <input type="hidden" name="duration" value="3_hours">
                                                                        <input type="hidden" name="ban_reason" value="Temporary 3-hour suspension">
                                                                        <button type="submit" class="dropdown-item py-1 rounded small">
                                                                            <i class="bi bi-clock-history text-warning me-2"></i> Ban for <strong>3 Hours</strong>
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                                <li>
                                                                    <form method="POST" action="admin.php?tab=users" class="m-0">
                                                                        <input type="hidden" name="action" value="ban_user">
                                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                                        <input type="hidden" name="duration" value="1_day">
                                                                        <input type="hidden" name="ban_reason" value="1-day suspension for community policy violation">
                                                                        <button type="submit" class="dropdown-item py-1 rounded small">
                                                                            <i class="bi bi-calendar-event text-danger me-2"></i> Ban for <strong>1 Day</strong>
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                                <li>
                                                                    <form method="POST" action="admin.php?tab=users" class="m-0">
                                                                        <input type="hidden" name="action" value="ban_user">
                                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                                        <input type="hidden" name="duration" value="5_days">
                                                                        <input type="hidden" name="ban_reason" value="5-day suspension for repeated violations">
                                                                        <button type="submit" class="dropdown-item py-1 rounded small text-danger fw-semibold">
                                                                            <i class="bi bi-shield-slash text-danger me-2"></i> Ban for <strong>5 Days</strong>
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Delete Account Form -->
                                                    <form method="POST" action="admin.php?tab=users" class="m-0 d-inline">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger confirm-delete" title="Delete Account" id="deleteUserBtn_<?php echo $u['id']; ?>" data-message="Permanently delete user account '<?php echo safe($u['name']); ?>'? All associated articles and comments will be deleted. This cannot be undone.">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 small" title="You are currently signed in as this administrator">
                                                        <i class="bi bi-shield-fill-check me-1"></i> Active Admin
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">No accounts matched the criteria.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Appoint Admin Modal -->
        <div class="modal fade" id="appointAdminModal" tabindex="-1" aria-labelledby="appointAdminModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-3">
                    <div class="modal-header bg-success text-white">
                        <h6 class="modal-title fw-bold mb-0" id="appointAdminModalLabel">
                            <i class="bi bi-shield-lock-fill me-2"></i>Appoint Co-Administrator
                        </h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="admin.php?tab=users" id="appointAdminModalForm">
                        <input type="hidden" name="action" value="appoint_admin">
                        <div class="modal-body p-4">
                            <div class="d-flex align-items-center gap-3 mb-3 p-3 bg-light rounded-3 border">
                                <div class="bg-success-subtle text-success p-2 rounded-circle fs-4 flex-shrink-0">
                                    <i class="bi bi-person-check-fill"></i>
                                </div>
                                <div class="small">
                                    <div class="fw-semibold text-dark">Grant Full Administrative Access</div>
                                    <div class="text-muted">Appointing an administrator allows them to publish news, manage categories, moderate Mindscape bricks, and manage user accounts.</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="appoint_user_id" class="form-label fw-semibold small">Select Student / User to Appoint <span class="text-danger">*</span></label>
                                <select class="form-select" id="appoint_user_id" name="user_id" required>
                                    <option value="">-- Choose registered student to promote --</option>
                                    <?php 
                                    $all_students_q = mysqli_query($conn, "SELECT id, name, email, student_id, faculty FROM users WHERE role != 'admin' ORDER BY name ASC");
                                    if ($all_students_q && mysqli_num_rows($all_students_q) > 0):
                                        while ($st = mysqli_fetch_assoc($all_students_q)):
                                            $st_info = safe($st['name']) . " (" . safe($st['email']) . ")";
                                            if (!empty($st['student_id'])) {
                                                $st_info .= " [" . safe($st['student_id']) . "]";
                                            }
                                            if (!empty($st['faculty'])) {
                                                $st_info .= " - " . safe($st['faculty']);
                                            }
                                    ?>
                                        <option value="<?php echo $st['id']; ?>" <?php echo ($appoint_id === intval($st['id'])) ? 'selected' : ''; ?>><?php echo $st_info; ?></option>
                                    <?php 
                                        endwhile;
                                    else: 
                                    ?>
                                        <option value="" disabled>All registered users are already administrators.</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="form-check p-2 bg-light rounded border mb-0 small">
                                <input class="form-check-input ms-1 me-2" type="checkbox" id="appointConfirmCheck" required>
                                <label class="form-check-label text-secondary" for="appointConfirmCheck">
                                    I confirm that this student should be granted administrator privileges on NSBM NewsDesk.
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-top">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success btn-sm px-3" id="confirmAppointAdminSubmitBtn">
                                <i class="bi bi-shield-check me-1"></i> Appoint as Admin
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <!-- Tab 7: Mindscape -->
    <?php elseif ($tab === 'mindscape'): ?>
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-light">
            <div class="card-body p-3">
                <form method="GET" action="admin.php" class="row g-2 align-items-center">
                    <input type="hidden" name="tab" value="mindscape">
                    <div class="col-md-8">
                        <input type="text" name="ms_search" class="form-control form-control-sm" placeholder="Search thought bricks by text, author, or student ID..." value="<?php echo safe($ms_search); ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-nsbm btn-sm w-100"><i class="bi bi-search me-1"></i> Filter</button>
                    </div>
                    <div class="col-md-2">
                        <a href="mindscape.php" target="_blank" class="btn btn-outline-success btn-sm w-100"><i class="bi bi-box-arrow-up-right me-1"></i> View Live Wall</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-bricks text-success me-2"></i>Mindscape Thought Bricks Moderation</h6>
                <span class="badge bg-light text-secondary border"><?php echo mysqli_num_rows($mindscape_list); ?> bricks posted</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-3 col-id">Brick #</th>
                                <th>Author</th>
                                <th>Thought Content</th>
                                <th>Brick Style</th>
                                <th>Reactions</th>
                                <th>Posted Time</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($mindscape_list && mysqli_num_rows($mindscape_list) > 0): ?>
                                <?php while ($brick = mysqli_fetch_assoc($mindscape_list)): ?>
                                    <tr>
                                        <td class="ps-3 text-muted small">#<?php echo $brick['id']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="fs-5 flex-shrink-0"><?php echo safe($brick['avatar_emoji'] ?? '💭'); ?></span>
                                                <div>
                                                    <div class="fw-semibold text-dark small"><?php echo safe($brick['author_name']); ?></div>
                                                    <?php if (!empty($brick['student_id'])): ?>
                                                        <span class="badge bg-light text-muted border text-xs"><?php echo safe($brick['student_id']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="small text-dark" style="max-width: 360px;">
                                            <div class="p-2 bg-light rounded-2 border">
                                                <?php echo nl2br(safe($brick['thought'])); ?>
                                                <?php if (!empty($brick['gif_url'])): ?>
                                                    <div class="mt-2 pt-2 border-top d-flex align-items-center gap-2">
                                                        <a href="<?php echo safe($brick['gif_url']); ?>" target="_blank" title="View Full GIF">
                                                            <img src="<?php echo safe($brick['gif_url']); ?>" alt="GIF" class="rounded border shadow-sm" style="height: 50px; max-width: 100px; object-fit: cover;">
                                                        </a>
                                                        <span class="badge bg-dark bg-opacity-75 text-white font-monospace text-xxs">
                                                            <i class="bi bi-filetype-gif me-1"></i>GIF
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border text-capitalize">
                                                <?php echo safe($brick['brick_color']); ?> &bull; <?php echo safe($brick['brick_size']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                ❤️ <?php echo intval($brick['likes']); ?>
                                            </span>
                                        </td>
                                        <td class="text-muted small">
                                            <?php echo date("M d, Y, g:i A", strtotime($brick['created_at'])); ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <form method="POST" action="admin.php?tab=mindscape" class="m-0 d-inline" onsubmit="return confirm('Are you sure you want to remove this thought brick from the Mindscape wall?');">
                                                <input type="hidden" name="tab" value="mindscape">
                                                <input type="hidden" name="action" value="delete_mindscape">
                                                <input type="hidden" name="thought_id" value="<?php echo $brick['id']; ?>">
                                                <input type="hidden" name="brick_id" value="<?php echo $brick['id']; ?>">
                                                <?php if (!empty($ms_search)): ?>
                                                    <input type="hidden" name="ms_search" value="<?php echo safe($ms_search); ?>">
                                                <?php endif; ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger confirm-delete" title="Remove Brick from Wall" data-message="Are you sure you want to remove this thought brick by '<?php echo safe($brick['author_name']); ?>' from the Mindscape wall?">
                                                    <i class="bi bi-trash me-1"></i> Remove
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">No thought bricks found on the wall.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- Tab 8: New Article -->
    <?php elseif ($tab === 'new'): ?>
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-pencil-square text-success me-2"></i>Post New News Article</h5>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <form method="POST" action="admin.php?tab=new" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="add_article">
                            <div class="mb-3">
                                <label for="title" class="form-label fw-semibold small">Article Headline <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" placeholder="e.g. NSBM Annual Research Symposium 2026" required>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="category_id" class="form-label fw-semibold small">Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="" disabled selected>Select category...</option>
                                        <?php 
                                        mysqli_data_seek($cat_options, 0);
                                        while ($c = mysqli_fetch_assoc($cat_options)): 
                                        ?>
                                            <option value="<?php echo $c['id']; ?>"><?php echo safe($c['name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="status" class="form-label fw-semibold small">Publishing Status <span class="text-danger">*</span></label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="approved" selected>Approved (Live Immediately)</option>
                                        <option value="pending">Draft / Pending</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Cover Image: File Upload or Link -->
                            <div class="mb-3 p-3 bg-light rounded-3 border article-cover-box">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-semibold small mb-0"><i class="bi bi-image me-1 text-success"></i>Cover Image</label>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <input type="radio" class="btn-check" name="adm_new_img_mode" id="adm_new_file" value="file" checked onchange="document.getElementById('admNewFileBox').classList.remove('d-none'); document.getElementById('admNewUrlBox').classList.add('d-none');">
                                        <label class="btn btn-outline-success" for="adm_new_file"><i class="bi bi-upload me-1"></i> Upload File</label>

                                        <input type="radio" class="btn-check" name="adm_new_img_mode" id="adm_new_url" value="url" onchange="document.getElementById('admNewFileBox').classList.add('d-none'); document.getElementById('admNewUrlBox').classList.remove('d-none');">
                                        <label class="btn btn-outline-success" for="adm_new_url"><i class="bi bi-link-45deg me-1"></i> Image Link</label>
                                    </div>
                                </div>
                                <div id="admNewFileBox">
                                    <input type="file" class="form-control form-control-sm" id="image_file" name="image_file" accept="image/*">
                                    <div class="form-text small">Upload an image file from your PC (saved to database uploads directory).</div>
                                </div>
                                <div id="admNewUrlBox" class="d-none">
                                    <input type="url" class="form-control form-control-sm" id="image_url" name="image_url" placeholder="https://images.unsplash.com/photo-...">
                                    <div class="form-text small">Or paste a direct web image URL.</div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="content" class="form-label fw-semibold small">Article Content <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="content" name="content" rows="10" placeholder="Type full story content here..." required></textarea>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="admin.php?tab=articles" class="btn btn-outline-secondary">Cancel</a>
                                <button type="submit" class="btn btn-nsbm px-4"><i class="bi bi-cloud-arrow-up me-1"></i> Publish Article</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <!-- Tab 9: Edit Article -->
    <?php elseif ($tab === 'edit' && $edit_article): ?>
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Article #<?php echo $edit_article['id']; ?></h5>
                        <a href="admin.php?tab=articles" class="btn btn-sm btn-outline-secondary">Back to Articles</a>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <form method="POST" action="admin.php?tab=edit" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="edit_article">
                            <input type="hidden" name="article_id" value="<?php echo $edit_article['id']; ?>">
                            <input type="hidden" name="existing_image_url" value="<?php echo safe($edit_article['image_url']); ?>">

                            <div class="mb-3">
                                <label for="edit_title" class="form-label fw-semibold small">Article Headline <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_title" name="title" value="<?php echo safe($edit_article['title']); ?>" required>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="edit_cat" class="form-label fw-semibold small">Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_cat" name="category_id" required>
                                        <?php 
                                        mysqli_data_seek($cat_options, 0);
                                        while ($c = mysqli_fetch_assoc($cat_options)): 
                                        ?>
                                            <option value="<?php echo $c['id']; ?>" <?php echo ($edit_article['category_id'] == $c['id']) ? 'selected' : ''; ?>>
                                                <?php echo safe($c['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_status" class="form-label fw-semibold small">Status <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_status" name="status" required>
                                        <option value="approved" <?php echo ($edit_article['status'] === 'approved') ? 'selected' : ''; ?>>Active / Published (Live)</option>
                                        <option value="restricted" <?php echo ($edit_article['status'] === 'restricted') ? 'selected' : ''; ?>>Restricted (Hidden from Public)</option>
                                        <option value="rejected" <?php echo ($edit_article['status'] === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Image edit options -->
                            <div class="mb-3 p-3 bg-light rounded-3 border article-cover-box">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-semibold small mb-0"><i class="bi bi-image me-1 text-success"></i>Cover Image</label>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <input type="radio" class="btn-check" name="adm_edit_img_mode" id="adm_edit_file" value="file" checked onchange="document.getElementById('admEditFileBox').classList.remove('d-none'); document.getElementById('admEditUrlBox').classList.add('d-none');">
                                        <label class="btn btn-outline-success" for="adm_edit_file"><i class="bi bi-upload me-1"></i> Upload File</label>

                                        <input type="radio" class="btn-check" name="adm_edit_img_mode" id="adm_edit_url" value="url" onchange="document.getElementById('admEditFileBox').classList.add('d-none'); document.getElementById('admEditUrlBox').classList.remove('d-none');">
                                        <label class="btn btn-outline-success" for="adm_edit_url"><i class="bi bi-link-45deg me-1"></i> Image Link</label>
                                    </div>
                                </div>
                                <div id="admEditFileBox">
                                    <input type="file" class="form-control form-control-sm" name="image_file" accept="image/*">
                                    <div class="form-text small">Upload a new image file to replace current. Leave empty to keep existing image.</div>
                                </div>
                                <div id="admEditUrlBox" class="d-none">
                                    <input type="url" class="form-control form-control-sm" id="edit_image" name="image_url" value="<?php echo safe($edit_article['image_url']); ?>" placeholder="https://images.unsplash.com/photo-...">
                                    <div class="form-text small">Enter an image link from the web.</div>
                                </div>
                                <?php if (!empty($edit_article['image_url'])): ?>
                                    <div class="mt-2">
                                        <span class="text-muted text-xs d-block mb-1">Current Image:</span>
                                        <img src="<?php echo safe($edit_article['image_url']); ?>" alt="Cover" class="rounded border shadow-sm" style="max-height: 110px; max-width: 200px; object-fit: cover;">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-4">
                                <label for="edit_content" class="form-label fw-semibold small">Story Content <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="edit_content" name="content" rows="10" required><?php echo safe($edit_article['content']); ?></textarea>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="admin.php?tab=articles" class="btn btn-outline-secondary">Cancel</a>
                                <button type="submit" class="btn btn-nsbm px-4"><i class="bi bi-check2 me-1"></i> Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Confirmation helper for data-confirm elements
    document.querySelectorAll('[data-confirm]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            var msg = this.getAttribute('data-confirm');
            if (msg && !confirm(msg)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });

    // Seamless AJAX Restrict / Lift Restriction toggle
    document.addEventListener('submit', function (e) {
        var form = e.target.closest('.form-restrict-toggle');
        if (!form) return;

        e.preventDefault();
        var submitBtn = form.querySelector('button[type="submit"]');
        var originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        }

        var formData = new FormData(form);
        formData.append('ajax', '1');

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function (res) {
            return res.json();
        })
        .then(function (data) {
            if (data && data.success) {
                var artId = data.article_id;
                var statusCell = document.getElementById('article_status_cell_' + artId);
                var btnBox = document.getElementById('restrict_btn_box_' + artId);

                if (data.status === 'restricted') {
                    if (statusCell) {
                        statusCell.innerHTML = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle" title="Restricted by admin"><i class="bi bi-slash-circle me-1"></i>Restricted</span>';
                    }
                    if (btnBox) {
                        btnBox.innerHTML = '<form method="POST" action="admin.php?tab=articles" class="m-0 d-inline form-restrict-toggle" data-id="' + artId + '" data-action="lift">' +
                            '<input type="hidden" name="action" value="unrestrict_article">' +
                            '<input type="hidden" name="article_id" value="' + artId + '">' +
                            '<button type="submit" id="btn_unrestrict_' + artId + '" class="btn btn-sm btn-outline-success" title="Lift Restriction (Publish Live)">' +
                            '<i class="bi bi-check-circle me-1"></i> Lift' +
                            '</button>' +
                            '</form>';
                    }
                } else {
                    if (statusCell) {
                        statusCell.innerHTML = '<span class="badge badge-approved"><i class="bi bi-check-circle-fill me-1"></i>Active</span>';
                    }
                    if (btnBox) {
                        btnBox.innerHTML = '<form method="POST" action="admin.php?tab=articles" class="m-0 d-inline form-restrict-toggle" data-id="' + artId + '" data-action="restrict">' +
                            '<input type="hidden" name="action" value="restrict_article">' +
                            '<input type="hidden" name="article_id" value="' + artId + '">' +
                            '<button type="submit" id="btn_restrict_' + artId + '" class="btn btn-sm btn-outline-warning" title="Restrict Article (Hide from public)">' +
                            '<i class="bi bi-slash-circle me-1"></i> Restrict' +
                            '</button>' +
                            '</form>';
                    }
                }

                // Show toast / notification
                var alertContainer = document.querySelector('.container .alert');
                if (alertContainer) {
                    alertContainer.className = 'alert alert-success alert-dismissible fade show shadow-sm';
                    alertContainer.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>' + (data.message || 'Status updated successfully.') + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                } else {
                    var newAlert = document.createElement('div');
                    newAlert.className = 'alert alert-success alert-dismissible fade show shadow-sm mb-4';
                    newAlert.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>' + (data.message || 'Status updated successfully.') + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                    var navPills = document.querySelector('.nav-pills');
                    if (navPills && navPills.parentNode) {
                        navPills.parentNode.insertBefore(newAlert, navPills);
                    }
                }
            } else {
                form.submit();
            }
        })
        .catch(function () {
            form.submit();
        });
    });

    <?php if ($appoint_id > 0 && $tab === 'users'): ?>
    var appointModalEl = document.getElementById('appointAdminModal');
    if (appointModalEl && typeof bootstrap !== 'undefined') {
        var appointModal = new bootstrap.Modal(appointModalEl);
        appointModal.show();
    }
    <?php endif; ?>
});
</script>

<?php include __DIR__ . "/includes/footer.php"; ?>

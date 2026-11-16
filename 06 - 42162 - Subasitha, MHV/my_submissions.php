<?php
require_once "db.php";

requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$msg = $_GET['msg'] ?? '';

// Handle student deleting their own article
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_article') {
    $del_id = intval($_POST['article_id'] ?? 0);
    if ($del_id > 0) {
        if (isAdmin()) {
            $del_stmt = mysqli_prepare($conn, "DELETE FROM articles WHERE id = ?");
            mysqli_stmt_bind_param($del_stmt, "i", $del_id);
        } else {
            $del_stmt = mysqli_prepare($conn, "DELETE FROM articles WHERE id = ? AND author_id = ?");
            mysqli_stmt_bind_param($del_stmt, "ii", $del_id, $user_id);
        }

        if (mysqli_stmt_execute($del_stmt) && mysqli_stmt_affected_rows($del_stmt) > 0) {
            header("Location: my_submissions.php?msg=" . urlencode("Your article has been deleted successfully."));
            exit;
        } else {
            $error = "Failed to delete article or permission denied.";
        }
        mysqli_stmt_close($del_stmt);
    }
}

// Retrieve articles submitted by the logged-in student
$query = "SELECT a.*, c.name AS category_name, c.slug AS category_slug 
          FROM articles a 
          JOIN categories c ON a.category_id = c.id 
          WHERE a.author_id = $user_id 
          ORDER BY a.created_at DESC";
$result = mysqli_query($conn, $query);

$pageTitle = "My Submissions";
include __DIR__ . "/includes/header.php";
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-journal-text text-success me-2"></i>My Article Submissions</h2>
            <p class="text-muted small mb-0">Track the review status of your campus news submissions.</p>
        </div>
        <a href="submit_article.php" class="btn btn-nsbm btn-sm">
            <i class="bi bi-plus-circle me-1"></i> Submit New Story
        </a>
    </div>

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

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="ps-4">Title</th>
                            <th scope="col">Category</th>
                            <th scope="col">Submitted Date</th>
                            <th scope="col">Review Status</th>
                            <th scope="col" class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td class="ps-4 fw-semibold">
                                        <?php echo safe($row['title']); ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-category badge-<?php echo safe($row['category_slug']); ?>">
                                            <?php echo safe($row['category_name']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small">
                                        <?php echo date("M d, Y", strtotime($row['created_at'])); ?>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'approved'): ?>
                                            <span class="badge badge-approved"><i class="bi bi-check-circle-fill me-1"></i>Published</span>
                                        <?php elseif ($row['status'] === 'restricted'): ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle" title="Restricted by admin"><i class="bi bi-slash-circle me-1"></i>Restricted</span>
                                        <?php else: ?>
                                            <span class="badge badge-pending"><i class="bi bi-hourglass-split me-1"></i><?php echo ucfirst($row['status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-inline-flex gap-1">
                                            <a href="view_article.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-secondary" title="View Story">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <a href="edit_article.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit Story">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <form method="POST" action="my_submissions.php" class="m-0 d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this article?');">
                                                <input type="hidden" name="action" value="delete_article">
                                                <input type="hidden" name="article_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Story">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-file-earmark-plus display-6 d-block mb-2"></i>
                                    You have not submitted any articles yet.
                                    <div class="mt-2">
                                        <a href="submit_article.php" class="btn btn-sm btn-nsbm">Submit Your First News Story</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/includes/footer.php"; ?>

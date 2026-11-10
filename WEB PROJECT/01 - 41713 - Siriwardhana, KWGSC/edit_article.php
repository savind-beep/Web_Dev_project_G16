<?php
require_once "db.php";

requireLogin();

$article_id = intval($_GET['id'] ?? ($_POST['article_id'] ?? 0));
if ($article_id <= 0) {
    header("Location: index.php");
    exit;
}

// Fetch article
$stmt = mysqli_prepare($conn, "SELECT * FROM articles WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $article_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$article = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$article) {
    header("Location: index.php?msg=" . urlencode("Article not found."));
    exit;
}

// Authorization check
$can_edit = isAdmin() || ($_SESSION['user_id'] == $article['author_id']);
if (!$can_edit) {
    header("Location: index.php?msg=" . urlencode("Access denied. You can only edit your own articles."));
    exit;
}

$error = "";
$msg = "";

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    // Handle image file or URL with fallback to existing
    $image_url = handleArticleImageUpload('image_file', 'image_url', $article['image_url']);

    if (isAdmin()) {
        $status = $_POST['status'] ?? $article['status'];
        if (!in_array($status, ['approved', 'restricted', 'rejected', 'pending'])) {
            $status = $article['status'];
        }
    } else {
        $status = $article['status'];
    }

    if (empty($title) || $category_id <= 0 || empty($content)) {
        $error = "Please fill in all required fields.";
    } else {
        $u_stmt = mysqli_prepare($conn, "UPDATE articles SET title = ?, category_id = ?, image_url = ?, content = ?, status = ? WHERE id = ?");
        mysqli_stmt_bind_param($u_stmt, "sisssi", $title, $category_id, $image_url, $content, $status, $article_id);

        if (mysqli_stmt_execute($u_stmt)) {
            mysqli_stmt_close($u_stmt);
            $redirect_url = isAdmin() ? "admin.php?tab=articles&msg=" : "view_article.php?id=" . $article_id . "&msg=";
            header("Location: " . $redirect_url . urlencode("Article updated successfully!"));
            exit;
        } else {
            $error = "Failed to update article: " . mysqli_error($conn);
            mysqli_stmt_close($u_stmt);
        }
    }
}

// Categories list
$categories_res = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");

$pageTitle = "Edit Article: " . $article['title'];
include __DIR__ . "/includes/header.php";
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-pencil-square text-success me-2"></i>Edit Article #<?php echo $article['id']; ?>
                    </h5>
                    <a href="<?php echo isAdmin() ? 'admin.php?tab=articles' : 'view_article.php?id=' . $article['id']; ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Back
                    </a>
                </div>
                <div class="card-body p-4 p-md-5">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger py-2 small mb-4">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo safe($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($article['status'] === 'restricted'): ?>
                        <div class="alert alert-warning border border-warning-subtle py-2 small mb-4 d-flex align-items-center">
                            <i class="bi bi-slash-circle-fill me-2 fs-5 text-warning"></i>
                            <div><strong>Notice:</strong> This article is currently restricted and hidden from public feeds.</div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="edit_article.php?id=<?php echo $article['id']; ?>" enctype="multipart/form-data">
                        <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">

                        <div class="mb-3">
                            <label for="title" class="form-label fw-semibold small">Article Headline <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo safe($article['title']); ?>" required>
                        </div>

                        <div class="row mb-3">
                            <div class="<?php echo isAdmin() ? 'col-md-6 mb-3 mb-md-0' : 'col-12'; ?>">
                                <label for="category_id" class="form-label fw-semibold small">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <?php 
                                    if ($categories_res) {
                                        mysqli_data_seek($categories_res, 0);
                                        while ($c = mysqli_fetch_assoc($categories_res)): 
                                    ?>
                                        <option value="<?php echo $c['id']; ?>" <?php echo ($article['category_id'] == $c['id']) ? 'selected' : ''; ?>>
                                            <?php echo safe($c['name']); ?>
                                        </option>
                                    <?php 
                                        endwhile; 
                                    }
                                    ?>
                                </select>
                            </div>

                            <?php if (isAdmin()): ?>
                                <div class="col-md-6">
                                    <label for="status" class="form-label fw-semibold small">Status <span class="text-danger">*</span></label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="approved" <?php echo ($article['status'] === 'approved') ? 'selected' : ''; ?>>Active / Published (Live)</option>
                                        <option value="restricted" <?php echo ($article['status'] === 'restricted') ? 'selected' : ''; ?>>Restricted (Hidden from Public)</option>
                                        <option value="rejected" <?php echo ($article['status'] === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Image Section with dual File / URL mode -->
                        <div class="mb-3 p-3 bg-light rounded-3 border">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-semibold small mb-0">
                                    <i class="bi bi-image me-1 text-success"></i> Cover Image
                                </label>
                                <div class="btn-group btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" name="edit_img_mode" id="emode_file" value="file" checked onchange="switchEditImgMode('file')">
                                    <label class="btn btn-outline-success" for="emode_file"><i class="bi bi-upload me-1"></i> Upload File</label>

                                    <input type="radio" class="btn-check" name="edit_img_mode" id="emode_url" value="url" onchange="switchEditImgMode('url')">
                                    <label class="btn btn-outline-success" for="emode_url"><i class="bi bi-link-45deg me-1"></i> Image Link</label>
                                </div>
                            </div>

                            <!-- File upload box -->
                            <div id="editBoxUpload">
                                <input type="file" class="form-control form-control-sm" id="image_file" name="image_file" accept="image/png, image/jpeg, image/jpg, image/webp, image/gif" onchange="previewEditUpload(this)">
                                <div class="form-text small">Upload a new image file from your computer (saved to database). Leave empty to keep the existing image.</div>
                            </div>

                            <!-- URL box -->
                            <div id="editBoxUrl" class="d-none">
                                <input type="url" class="form-control form-control-sm" id="image_url" name="image_url" value="<?php echo safe($article['image_url']); ?>" placeholder="https://images.unsplash.com/..." oninput="previewEditUrl(this.value)">
                                <div class="form-text small">Enter an image link from the web.</div>
                            </div>

                            <!-- Live preview or current image -->
                            <div id="editImgPreviewArea" class="mt-2">
                                <span class="text-muted text-xs d-block mb-1">Image Preview:</span>
                                <div class="d-inline-block position-relative border rounded p-1 bg-white shadow-sm">
                                    <img id="editPreviewImg" src="<?php echo safe($article['image_url']); ?>" alt="Cover Image" style="max-height: 130px; max-width: 240px; object-fit: cover; border-radius: 4px; display: block;" onerror="this.style.display='none';">
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="content" class="form-label fw-semibold small">Story Content <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="content" name="content" rows="10" required><?php echo safe($article['content']); ?></textarea>
                        </div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3 border-top">
                            <div class="d-flex gap-2">
                                <a href="<?php echo isAdmin() ? 'admin.php?tab=articles' : 'view_article.php?id=' . $article['id']; ?>" class="btn btn-outline-secondary">
                                    Cancel
                                </a>
                                <button type="submit" form="deleteArticleForm" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to permanently delete this article?');">
                                    <i class="bi bi-trash me-1"></i> Delete Story
                                </button>
                            </div>
                            <button type="submit" class="btn btn-nsbm px-4">
                                <i class="bi bi-check2 me-1"></i> Save Changes
                            </button>
                        </div>
                    </form>
                    <form id="deleteArticleForm" method="POST" action="my_submissions.php" class="d-none">
                        <input type="hidden" name="action" value="delete_article">
                        <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchEditImgMode(mode) {
    const boxUpload = document.getElementById('editBoxUpload');
    const boxUrl = document.getElementById('editBoxUrl');
    if (mode === 'file') {
        boxUpload.classList.remove('d-none');
        boxUrl.classList.add('d-none');
    } else {
        boxUpload.classList.add('d-none');
        boxUrl.classList.remove('d-none');
    }
}

function previewEditUpload(input) {
    const previewImg = document.getElementById('editPreviewImg');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            previewImg.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function previewEditUrl(val) {
    const previewImg = document.getElementById('editPreviewImg');
    val = val.trim();
    if (val && (val.startsWith('http://') || val.startsWith('https://') || val.startsWith('uploads/'))) {
        previewImg.src = val;
        previewImg.style.display = 'block';
    }
}
</script>

<?php include __DIR__ . "/includes/footer.php"; ?>

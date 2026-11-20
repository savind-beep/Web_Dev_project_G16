<?php
require_once "db.php";

requireLogin();

$error = trim($_GET['error'] ?? '');
$msg = trim($_GET['msg'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    $author_id = $_SESSION['user_id'];
    $status = 'approved';

    // Process image: file upload or URL link
    $image_url = handleArticleImageUpload('image_file', 'image_url');

    if (empty($title) || $category_id <= 0 || empty($content)) {
        $error = "Please fill in all required fields.";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO articles (title, category_id, author_id, content, image_url, status) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "siisss", $title, $category_id, $author_id, $content, $image_url, $status);

        if (mysqli_stmt_execute($stmt)) {
            $new_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            header("Location: view_article.php?id=" . $new_id . "&msg=" . urlencode("Your article has been published live!"));
            exit;
        } else {
            $error = "Failed to submit article: " . mysqli_error($conn);
            mysqli_stmt_close($stmt);
        }
    }
}

// Categories list
$categories_res = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");

$pageTitle = "Submit News Story";
include __DIR__ . "/includes/header.php";
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-center gap-3 mb-4 border-bottom pb-3">
                        <div class="bg-success-subtle text-success icon-circle flex-shrink-0">
                            <i class="bi bi-pencil-square fs-3"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-1">Submit a News Story</h3>
                            <p class="text-muted small mb-0">Share university events, achievements, society notices, or campus announcements.</p>
                        </div>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo safe($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($msg)): ?>
                        <div class="alert alert-success alert-dismissible fade show small" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i> <?php echo safe($msg); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-success-subtle border border-success-subtle py-2 small d-flex align-items-center mb-4 text-success-emphasis">
                        <i class="bi bi-broadcast fs-5 me-2 flex-shrink-0 text-success"></i>
                        <div>Your article will be published instantly across NSBM Green Gazette upon submission. Administrators may restrict content that breaches campus guidelines.</div>
                    </div>

                    <!-- Submission Form with file upload support -->
                    <form method="POST" action="submit_article.php" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="title" class="form-label fw-semibold small">Article Headline / Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" placeholder="e.g. NSBM Computing Club Hosts National Hackathon" required value="<?php echo safe($_POST['title'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="category_id" class="form-label fw-semibold small">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="" disabled <?php echo empty($_POST['category_id']) ? 'selected' : ''; ?>>Select category...</option>
                                <?php if ($categories_res): ?>
                                    <?php while ($cat = mysqli_fetch_assoc($categories_res)): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo safe($cat['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Image Upload or Link Option -->
                        <div class="mb-3 p-3 bg-light rounded-3 border">
                            <label class="form-label fw-semibold small d-block mb-2">
                                <i class="bi bi-image me-1 text-success"></i> Cover Image
                            </label>

                            <!-- Toggle between File and URL -->
                            <div class="btn-group btn-group-sm mb-3" role="group">
                                <input type="radio" class="btn-check" name="image_type_option" id="opt_file" value="file" checked onchange="switchImageMode('file')">
                                <label class="btn btn-outline-success" for="opt_file"><i class="bi bi-upload me-1"></i> Upload File</label>

                                <input type="radio" class="btn-check" name="image_type_option" id="opt_url" value="url" onchange="switchImageMode('url')">
                                <label class="btn btn-outline-success" for="opt_url"><i class="bi bi-link-45deg me-1"></i> Paste Image Link</label>
                            </div>

                            <!-- File upload input box -->
                            <div id="boxUploadFile">
                                <input type="file" class="form-control form-control-sm" id="image_file" name="image_file" accept="image/png, image/jpeg, image/jpg, image/webp, image/gif" onchange="previewSelectedFile(this)">
                                <div class="form-text small">Upload an image from your device (JPG, PNG, WEBP, GIF). It will be saved into the database uploads folder.</div>
                            </div>

                            <!-- Image URL input box -->
                            <div id="boxImageUrl" class="d-none">
                                <input type="url" class="form-control form-control-sm" id="image_url" name="image_url" placeholder="https://images.unsplash.com/photo-..." oninput="previewEnteredUrl(this.value)">
                                <div class="form-text small">Paste any direct web link to an image.</div>
                            </div>

                            <!-- Live preview container -->
                            <div id="imageLivePreview" class="d-none mt-2">
                                <div class="d-inline-block position-relative border rounded p-1 bg-white shadow-sm">
                                    <img id="imgPreviewTarget" src="" alt="Preview" style="max-height: 120px; max-width: 220px; object-fit: cover; border-radius: 4px; display: block;">
                                    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 py-0 px-1 rounded-circle" style="font-size: 0.75rem;" onclick="resetImageSelection()" title="Clear">&times;</button>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="content" class="form-label fw-semibold small">Story Content <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="content" name="content" rows="7" placeholder="Write the details of the news announcement, schedule, or event highlights..." required><?php echo safe($_POST['content'] ?? ''); ?></textarea>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-nsbm text-white fw-bold px-4 shadow-sm">
                                <i class="bi bi-send-fill me-1"></i> Submit for Review
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchImageMode(mode) {
    const boxFile = document.getElementById('boxUploadFile');
    const boxUrl = document.getElementById('boxImageUrl');
    if (mode === 'file') {
        boxFile.classList.remove('d-none');
        boxUrl.classList.add('d-none');
        document.getElementById('image_url').value = '';
    } else {
        boxFile.classList.add('d-none');
        boxUrl.classList.remove('d-none');
        document.getElementById('image_file').value = '';
    }
}

function previewSelectedFile(input) {
    const previewContainer = document.getElementById('imageLivePreview');
    const previewImg = document.getElementById('imgPreviewTarget');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            previewContainer.classList.remove('d-none');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function previewEnteredUrl(val) {
    const previewContainer = document.getElementById('imageLivePreview');
    const previewImg = document.getElementById('imgPreviewTarget');
    val = val.trim();
    if (val && (val.startsWith('http://') || val.startsWith('https://'))) {
        previewImg.src = val;
        previewContainer.classList.remove('d-none');
    } else {
        previewContainer.classList.add('d-none');
    }
}

function resetImageSelection() {
    document.getElementById('image_file').value = '';
    document.getElementById('image_url').value = '';
    document.getElementById('imageLivePreview').classList.add('d-none');
    document.getElementById('imgPreviewTarget').src = '';
}
</script>

<?php include __DIR__ . "/includes/footer.php"; ?>

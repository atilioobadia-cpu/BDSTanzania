<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
bdst_require_login();

$pdo = null;
$post = null;
$id = max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
$isEdit = $id > 0;

try {
    $pdo = bdst_db();
    if ($isEdit) {
        $stmt = $pdo->prepare("SELECT * FROM updates WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $post = $stmt->fetch();
        if (!$post) {
            bdst_flash('error', 'Update not found.');
            header('Location: dashboard.php');
            exit;
        }
    }
} catch (Throwable $e) {
    error_log('Admin post load error: ' . $e->getMessage());
    bdst_flash('error', 'Could not connect to the database: ' . $e->getMessage());
    header('Location: dashboard.php');
    exit;
}

function bdst_slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string) $text, '-') ?: 'update';
}

function bdst_unique_slug(PDO $pdo, string $slug, int $ignoreId): string
{
    $base = $slug;
    $i    = 2;
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM updates WHERE slug = :slug AND id <> :id");
        $stmt->bindValue(':slug', $slug);
        $stmt->bindValue(':id', $ignoreId, PDO::PARAM_INT);
        $stmt->execute();
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
}

function bdst_handle_upload(array $file, string $targetDir): array
{
    // Returns [bool $ok, string $error, string $relativePath]
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [true, '', ''];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return [false, 'Image upload failed.', ''];
    }
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return [false, 'Image must be 2 MB or smaller.', ''];
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return [false, 'The uploaded file is not a valid image.', ''];
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    $mime = $info['mime'] ?? '';
    if (!isset($allowed[$mime])) {
        return [false, 'Only JPG, PNG, WEBP or GIF images are allowed.', ''];
    }

    if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
        return [false, 'Could not create the uploads directory.', ''];
    }

    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    if (!@move_uploaded_file($file['tmp_name'], $targetDir . '/' . $name)) {
        return [false, 'Could not save the uploaded image.', ''];
    }

    return [true, '', 'assets/images/uploads/' . $name];
}

$saved = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bdst_csrf_verify($_POST['csrf'] ?? null)) {
        bdst_flash('error', 'Invalid security token. Please try again.');
        header('Location: post.php' . ($isEdit ? '?id=' . $id : ''));
        exit;
    }

    $title       = trim((string) ($_POST['title'] ?? ''));
    $excerpt     = trim((string) ($_POST['excerpt'] ?? ''));
    $content     = (string) ($_POST['content'] ?? '');
    $status      = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $publishedAt = trim((string) ($_POST['published_at'] ?? ''));
    $keepImage   = (string) ($_POST['existing_image'] ?? '');
    $deleteImage = isset($_POST['remove_image']);

    if ($title === '') {
        $saved = [false, 'Title is required.'];
    } else {
        if ($publishedAt === '') {
            $publishedAt = $post['published_at'] ?? date('Y-m-d H:i:s');
        }

        [$uploadOk, $uploadError, $uploadPath] = bdst_handle_upload(
            $_FILES['image'] ?? [],
            __DIR__ . '/../assets/images/uploads'
        );

        if (!$uploadOk) {
            $saved = [false, $uploadError];
        } else {
            $imagePath = $post['image_path'] ?? '';
            if ($uploadPath !== '') {
                $imagePath = $uploadPath;
            } elseif ($deleteImage) {
                $imagePath = '';
            } else {
                $imagePath = $keepImage;
            }

            $slugInput = trim((string) ($_POST['slug'] ?? ''));
            $slug      = $slugInput !== '' ? bdst_slugify($slugInput) : bdst_slugify($title);
            $slug      = bdst_unique_slug($pdo, $slug, $id);

            try {
                if ($isEdit) {
                    $stmt = $pdo->prepare(
                        "UPDATE updates
                         SET title = :title, slug = :slug, excerpt = :excerpt, content = :content,
                             image_path = :image, status = :status, published_at = :published_at
                         WHERE id = :id"
                    );
                    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO updates (title, slug, excerpt, content, image_path, status, published_at)
                         VALUES (:title, :slug, :excerpt, :content, :image, :status, :published_at)"
                    );
                }
                $stmt->bindValue(':title', $title);
                $stmt->bindValue(':slug', $slug);
                $stmt->bindValue(':excerpt', $excerpt !== '' ? $excerpt : null);
                $stmt->bindValue(':content', $content !== '' ? $content : null);
                $stmt->bindValue(':image', $imagePath !== '' ? $imagePath : null);
                $stmt->bindValue(':status', $status);
                $stmt->bindValue(':published_at', $publishedAt);
                $stmt->execute();

                bdst_flash('success', $isEdit ? 'Update saved successfully.' : 'Update published successfully.');
                header('Location: dashboard.php');
                exit;
            } catch (Throwable $e) {
                error_log('Admin save error: ' . $e->getMessage());
                $saved = [false, 'Could not save the update: ' . $e->getMessage()];
            }
        }
    }
}

$title       = $post['title'] ?? '';
$excerpt     = $post['excerpt'] ?? '';
$content     = $post['content'] ?? '';
$status      = $post['status'] ?? 'draft';
$publishedAt = $post['published_at'] ?? '';
$imagePath   = $post['image_path'] ?? '';

$flash = bdst_flash_consume();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= bdst_admin_title($isEdit ? 'Edit Update' : 'New Update') ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="../assets/images/fav.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-body">
    <aside class="sidebar">
        <a class="sidebar-brand" href="dashboard.php">
            <img src="../assets/images/logos/logo.webp" alt="BDS Tanzania" height="34">
        </a>
        <nav class="sidebar-nav">
            <a class="nav-link" href="dashboard.php">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg></span>
                <span>Dashboard</span>
            </a>
            <a class="nav-link active" href="post.php">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg></span>
                <span>New Update</span>
            </a>
        </nav>
        <div class="sidebar-foot">
            <a class="nav-link" href="../index.html" target="_blank" rel="noopener">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg></span>
                <span>View Site</span>
            </a>
            <a class="nav-link" href="logout.php">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
                <span>Log Out</span>
            </a>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar-inner">
                <div class="page-title-block">
                    <h1><?= $isEdit ? 'Edit Update' : 'New Update' ?></h1>
                    <p><?= $isEdit ? 'Update the details below and save your changes.' : 'Fill in the details below to publish a news update.' ?></p>
                </div>
                <a href="dashboard.php" class="btn btn-ghost">&larr; Back to Dashboard</a>
            </div>
        </header>

        <div class="page">

        <?php if ($flash): ?>
            <div class="alert alert-<?= bdst_e($flash['type']) ?>"><?= bdst_e($flash['message']) ?></div>
        <?php endif; ?>

        <?php if (is_array($saved) && $saved[0] === false): ?>
            <div class="alert alert-error"><?= bdst_e($saved[1]) ?></div>
        <?php endif; ?>

        <form method="post" action="post.php<?= $isEdit ? '?id=' . $id : '' ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= bdst_e(bdst_csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="existing_image" value="<?= bdst_e($imagePath) ?>">

            <div class="grid">
                <div class="panel col-main">
                    <div class="field">
                        <label for="title">Title *</label>
                        <input type="text" id="title" name="title" value="<?= bdst_e($title) ?>" required maxlength="200" placeholder="e.g. BDS Tanzania launches national practitioner registry">
                    </div>

                    <div class="field">
                        <label for="excerpt">Short Description</label>
                        <input type="text" id="excerpt" name="excerpt" value="<?= bdst_e($excerpt) ?>" maxlength="500" placeholder="One or two sentences shown on the Updates page.">
                    </div>

                    <div class="field">
                        <label for="content">Full Content</label>
                        <textarea id="content" name="content" rows="14" placeholder="Write the full update here. Basic HTML is allowed (p, br, h2, h3, ul, ol, li, strong, em, a)."><?= bdst_e($content) ?></textarea>
                    </div>
                </div>

                <aside class="panel col-side">
                    <h3 class="panel-title">Publishing</h3>

                    <div class="field">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="published_at">Publish Date</label>
                        <input type="datetime-local" id="published_at" name="published_at" value="<?= bdst_e($publishedAt !== '' ? date('Y-m-d\TH:i', strtotime($publishedAt)) : '') ?>">
                    </div>

                    <div class="field">
                        <label for="slug">URL Slug <span class="muted">(optional)</span></label>
                        <input type="text" id="slug" name="slug" value="<?= bdst_e($post['slug'] ?? '') ?>" maxlength="220" placeholder="auto-generated from title">
                    </div>

                    <hr class="divider">

                    <h3 class="panel-title">Featured Image</h3>

                    <?php if ($imagePath !== ''): ?>
                        <div class="preview-img">
                            <img src="../<?= bdst_e($imagePath) ?>" alt="Current image">
                        </div>
                        <label class="checkbox">
                            <input type="checkbox" name="remove_image" value="1">
                            <span>Remove current image</span>
                        </label>
                    <?php endif; ?>

                    <div class="field">
                        <label for="image"><?= $imagePath !== '' ? 'Replace Image' : 'Upload Image' ?></label>
                        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                        <p class="hint">JPG, PNG, WEBP or GIF. Max 2 MB.</p>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block"><?= $isEdit ? 'Save Changes' : 'Create Update' ?></button>
                </aside>
            </div>
        </form>
        </div>
    </main>
</body>
</html>

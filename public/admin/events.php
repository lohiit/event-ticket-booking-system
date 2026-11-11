<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/../../app/helpers/view.php';
require_once __DIR__ . '/../../app/helpers/validation.php';
require_once __DIR__ . '/../../app/helpers/event-image.php';

requireAdmin();
$pdo = db();
$error = '';
$formValues = ['title' => '', 'description' => '', 'category' => '', 'status' => 'draft'];
$editing = null;
$newImagePath = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    if (!hasValidCsrfToken(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null)) {
        $error = 'Your session expired. Refresh and try again.';
    } else {
        $formValues = [
            'title' => formString('title', 200),
            'description' => formString('description', 5000),
            'category' => formString('category', 80),
            'status' => formString('status', 20),
        ];

        try {
            if ($id > 0) {
                $lookup = $pdo->prepare('SELECT status, image_path FROM events WHERE event_id = ?');
                $lookup->execute([$id]);
                $existing = $lookup->fetch();
                if (!$existing) {
                    throw new InvalidArgumentException('That event could not be found.');
                }
                $editing = ['event_id' => $id, ...$formValues, 'image_path' => $existing['image_path']];
                if ($existing['status'] === 'cancelled') {
                    $formValues['status'] = 'cancelled';
                }
            } else {
                $existing = null;
            }

            if ($formValues['title'] === '' || !in_array($formValues['status'], ['draft', 'published', 'cancelled'], true)
                || ($formValues['status'] === 'cancelled' && ($existing['status'] ?? null) !== 'cancelled')) {
                throw new InvalidArgumentException('Enter an event title and choose Draft or Published.');
            }

            $upload = $_FILES['event_image'] ?? null;
            if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $newImagePath = saveEventImage($upload);
            }

            $imagePath = $newImagePath ?? ($existing['image_path'] ?? null);
            if ($id > 0) {
                $statement = $pdo->prepare(
                    'UPDATE events SET title=:title, description=:description, category=:category,
                     status=:status, image_path=:image_path WHERE event_id=:event_id'
                );
                $statement->execute([
                    'title' => $formValues['title'],
                    'description' => $formValues['description'] ?: null,
                    'category' => $formValues['category'] ?: null,
                    'status' => $formValues['status'],
                    'image_path' => $imagePath,
                    'event_id' => $id,
                ]);
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO events (title, description, category, status, image_path)
                     VALUES (:title, :description, :category, :status, :image_path)'
                );
                $statement->execute([
                    'title' => $formValues['title'],
                    'description' => $formValues['description'] ?: null,
                    'category' => $formValues['category'] ?: null,
                    'status' => $formValues['status'],
                    'image_path' => $imagePath,
                ]);
            }

            if ($newImagePath !== null && isset($existing['image_path'])) {
                deleteEventImage($existing['image_path']);
            }
            header('Location: /admin/events.php?saved=1', true, 303);
            exit;
        } catch (InvalidArgumentException $exception) {
            if ($newImagePath !== null) {
                deleteEventImage($newImagePath);
            }
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($newImagePath !== null) {
                deleteEventImage($newImagePath);
            }
            error_log('Admin event save failed: ' . $exception->getMessage());
            $error = 'Could not save the event. Check the fields and image, then try again.';
        }
    }
}

$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editing === null && $editId) {
    $statement = $pdo->prepare('SELECT * FROM events WHERE event_id = ?');
    $statement->execute([$editId]);
    $editing = $statement->fetch() ?: null;
    if ($editing) {
        $formValues = [
            'title' => (string) $editing['title'],
            'description' => (string) ($editing['description'] ?? ''),
            'category' => (string) ($editing['category'] ?? ''),
            'status' => (string) $editing['status'],
        ];
    }
}

$events = $pdo->query(
    "SELECT e.event_id, e.title, e.category, e.status, e.image_path,
            (SELECT COUNT(*) FROM shows s WHERE s.event_id=e.event_id) show_count
     FROM events e ORDER BY e.updated_at DESC"
)->fetchAll();
$pageTitle = 'Manage events';
$bodyClass = 'admin-page admin-events-page';
require_once __DIR__ . '/../../app/includes/header.php';
?>
<section class="admin-workspace">
    <div class="admin-heading">
        <div><p class="eyebrow">CONTROL ROOM / CATALOGUE</p><h1>Events</h1><p>Build the listings people come to see.</p></div>
        <a class="text-link" href="/admin/">Back to dashboard <span aria-hidden="true">↗</span></a>
    </div>

    <?php if ($error !== ''): ?><p class="admin-notice admin-notice--error" role="alert"><?= e($error) ?></p>
    <?php elseif (isset($_GET['saved'])): ?><p class="admin-notice">Event saved.</p><?php endif; ?>

    <div class="admin-event-columns">
        <form class="admin-event-form" method="post" enctype="multipart/form-data">
            <div class="admin-form-heading"><p class="eyebrow"><?= $editing ? 'EDIT LISTING' : 'NEW LISTING' ?></p><h2><?= $editing ? 'Event details' : 'Create an event' ?></h2></div>
            <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="event_id" value="<?= (int) ($editing['event_id'] ?? 0) ?>">

            <label for="title">Event name</label>
            <input id="title" name="title" maxlength="200" required placeholder="Give it a name people remember" value="<?= e($formValues['title']) ?>">

            <div class="admin-form-row">
                <div><label for="category">Category</label><input id="category" name="category" maxlength="80" placeholder="Music, arts, theatre…" value="<?= e($formValues['category']) ?>"></div>
                <?php if (($editing['status'] ?? '') === 'cancelled'): ?>
                    <div><label>Listing status</label><p class="admin-status admin-status--cancelled">Cancelled · locked</p><input type="hidden" name="status" value="cancelled"></div>
                <?php else: ?>
                    <div><label for="status">Listing status</label><select id="status" name="status"><option value="draft" <?= $formValues['status'] === 'draft' ? 'selected' : '' ?>>Draft</option><option value="published" <?= $formValues['status'] === 'published' ? 'selected' : '' ?>>Published</option></select></div>
                <?php endif; ?>
            </div>

            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" maxlength="5000" placeholder="What should guests know about this event?"><?= e($formValues['description']) ?></textarea>

            <label class="poster-label" for="event_image">Poster image <span>JPEG, PNG or WebP · up to 2 MB</span></label>
            <div class="poster-picker">
                <?php [$previewPath, $previewAlt] = eventArtwork($formValues['title'], $editing['image_path'] ?? null); ?>
                <img id="poster-preview" src="<?= e($previewPath) ?>" alt="<?= e($previewAlt) ?>">
                <div><strong><?= $editing && !empty($editing['image_path']) ? 'Replace this poster' : 'Add a poster' ?></strong><p>Use a landscape image for the cleanest crop.</p><input id="event_image" name="event_image" type="file" accept="image/jpeg,image/png,image/webp"></div>
            </div>

            <button class="btn admin-save" type="submit"><?= $editing ? 'Save changes' : 'Create event' ?><span aria-hidden="true">→</span></button>
        </form>

        <div class="admin-event-list">
            <div class="admin-list-heading"><div><p class="eyebrow">YOUR CATALOGUE</p><h2>Event listings</h2></div><span><?= count($events) ?> total</span></div>
            <?php foreach ($events as $event): ?>
                <?php [$posterPath, $posterAlt] = eventArtwork((string) $event['title'], $event['image_path'] ?? null); ?>
                <article class="admin-event-row">
                    <img src="<?= e($posterPath) ?>" alt="<?= e($posterAlt) ?>" loading="lazy">
                    <div class="admin-event-row-copy"><span class="admin-event-meta"><?= e($event['category'] ?: 'Uncategorized') ?> · <?= (int) $event['show_count'] ?> shows</span><h3><?= e($event['title']) ?></h3><span class="admin-status admin-status--<?= e($event['status']) ?>"><?= e(ucfirst($event['status'])) ?></span></div>
                    <a class="admin-edit-link" href="?edit=<?= (int) $event['event_id'] ?>" aria-label="Edit <?= e($event['title']) ?>">Edit <span aria-hidden="true">↗</span></a>
                </article>
            <?php endforeach; ?>
            <?php if ($events === []): ?><p class="admin-empty">Your first event will appear here.</p><?php endif; ?>
        </div>
    </div>
</section>
<script>
document.getElementById('event_image').addEventListener('change', (event) => {
    const file = event.target.files && event.target.files[0];
    if (file) document.getElementById('poster-preview').src = URL.createObjectURL(file);
});
</script>
<?php require_once __DIR__ . '/../../app/includes/footer.php'; ?>

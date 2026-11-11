<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/../../app/helpers/view.php';
require_once __DIR__ . '/../../app/helpers/validation.php';
requireAdmin(); $pdo=db(); $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hasValidCsrfToken(is_string($_POST['_csrf']??null)?$_POST['_csrf']:null)) $error='Session expired. Refresh and try again.';
    else {
        $id=filter_var($_POST['event_id']??null,FILTER_VALIDATE_INT)?:0; $title=formString('title',200); $description=formString('description',5000); $category=formString('category',80); $status=formString('status',20);
        $existingStatus=null; if($id>0){$current=$pdo->prepare('SELECT status FROM events WHERE event_id=?');$current->execute([$id]);$existingStatus=$current->fetchColumn();if($existingStatus===false){$existingStatus=null;$error='Event not found.';}}
        if($existingStatus==='cancelled')$status='cancelled';
        if($error!==''||$title===''||!in_array($status,['draft','published'],true)&&$status!=='cancelled') $error=$error?:'Enter a title and valid status.';
        else try { $st=$id>0?$pdo->prepare('UPDATE events SET title=:title, description=:description, category=:category, status=:status WHERE event_id=:id'):$pdo->prepare('INSERT INTO events(title,description,category,status) VALUES(:title,:description,:category,:status)'); $data=['title'=>$title,'description'=>$description?:null,'category'=>$category?:null,'status'=>$status]; if($id>0)$data['id']=$id; $st->execute($data); header('Location: /admin/events.php?saved=1',true,303); exit; }
        catch(Throwable $e){error_log('Admin event save failed: '.$e->getMessage());$error='Could not save the event.';}
    }
}
$editing=null; $editId=filter_input(INPUT_GET,'edit',FILTER_VALIDATE_INT); if($editId){$st=$pdo->prepare('SELECT * FROM events WHERE event_id=?');$st->execute([$editId]);$editing=$st->fetch()?:null;}
$events=$pdo->query("SELECT e.*, (SELECT COUNT(*) FROM shows s WHERE s.event_id=e.event_id) show_count FROM events e ORDER BY e.updated_at DESC")->fetchAll();
$pageTitle='Manage events'; require_once __DIR__.'/../../app/includes/header.php';
?>
<section class="featured-events"><div class="section-heading"><p class="eyebrow"><a href="/admin/">ADMIN</a></p><h1>Events</h1><p>Create and edit event listings. Publishing makes scheduled shows visible to customers.</p></div>
<?php if($error!==''):?><p class="form-message form-message--error" role="alert"><?=e($error)?></p><?php elseif(isset($_GET['saved'])):?><p class="form-message">Event saved.</p><?php endif;?>
<form class="auth-form" method="post"><h2><?=$editing?'Edit event':'Add event'?></h2><input type="hidden" name="_csrf" value="<?=e(csrfToken())?>"><input type="hidden" name="event_id" value="<?=(int)($editing['event_id']??0)?>"><label for="title">Title</label><input id="title" name="title" maxlength="200" required value="<?=e((string)($editing['title']??''))?>"><label for="category">Category</label><input id="category" name="category" maxlength="80" value="<?=e((string)($editing['category']??''))?>"><label for="description">Description</label><textarea id="description" name="description" rows="4" maxlength="5000"><?=e((string)($editing['description']??''))?></textarea><?php if(($editing['status']??'')==='cancelled'):?><p class="form-message">This event is cancelled; its status cannot be changed here.</p><input type="hidden" name="status" value="cancelled"><?php else:?><label for="status">Status</label><select id="status" name="status"><option value="draft" <?=($editing['status']??'draft')==='draft'?'selected':''?>>Draft</option><option value="published" <?=($editing['status']??'')==='published'?'selected':''?>>Published</option></select><?php endif;?><button class="btn" type="submit">Save event</button></form>
<div class="event-grid"><?php foreach($events as $event):?><article class="event-card"><div class="event-card-content"><span class="event-category"><?=e($event['status'])?> · <?=(int)$event['show_count']?> shows</span><h2><?=e($event['title'])?></h2><p><?=e($event['category']??'')?></p><a class="btn" href="?edit=<?=(int)$event['event_id']?>">Edit</a></div></article><?php endforeach;?></div></section>
<?php require_once __DIR__.'/../../app/includes/footer.php'; ?>

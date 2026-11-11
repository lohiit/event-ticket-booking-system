<?php
declare(strict_types=1);
require_once __DIR__.'/../../app/config/database.php'; require_once __DIR__.'/../../app/helpers/auth.php'; require_once __DIR__.'/../../app/helpers/view.php'; require_once __DIR__.'/../../app/helpers/validation.php';
requireAdmin(); $pdo=db(); $error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hasValidCsrfToken(is_string($_POST['_csrf']??null)?$_POST['_csrf']:null))$error='Session expired. Refresh and try again.';
 else{$name=formString('name',160);$address=formString('address',500);$id=filter_var($_POST['venue_id']??null,FILTER_VALIDATE_INT)?:0;
  try{if($name==='')throw new InvalidArgumentException('Enter a venue name.');
   if($id>0){$st=$pdo->prepare('UPDATE venues SET name=?,address=? WHERE venue_id=?');$st->execute([$name,$address?:null,$id]);}
   else{$rows=[];foreach(preg_split('/\R/',trim(formString('layout',10000))) as $line){if(trim($line)==='')continue;$p=array_map('trim',explode(',',$line));if(count($p)!==3||$p[0]===''||!preg_match('/^[A-Za-z0-9_-]{1,20}$/',$p[1])||!preg_match('/^[1-9][0-9]{0,2}$/',$p[2]))throw new InvalidArgumentException('Each seat row must be Section,Row,SeatCount (example: Stalls,A,10).');for($n=1;$n<=(int)$p[2];$n++)$rows[]=[$p[0],$p[1],(string)$n];if(count($rows)>1000)throw new InvalidArgumentException('A venue can have at most 1,000 seats.');}if(!$rows)throw new InvalidArgumentException('Add at least one seat row.');$pdo->beginTransaction();$st=$pdo->prepare('INSERT INTO venues(name,address) VALUES(?,?)');$st->execute([$name,$address?:null]);$vid=(int)$pdo->lastInsertId();$seat=$pdo->prepare('INSERT INTO seats(venue_id,section_name,row_label,seat_number) VALUES(?,?,?,?)');foreach($rows as $r)$seat->execute([$vid,...$r]);$pdo->commit();}
   header('Location: /admin/venues.php?saved=1',true,303);exit;
  }catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Admin venue save failed: '.$e->getMessage());$error='Could not save the venue. Check the name and seat layout.';}
 }
}
$edit=filter_input(INPUT_GET,'edit',FILTER_VALIDATE_INT);$editing=null;if($edit){$st=$pdo->prepare('SELECT * FROM venues WHERE venue_id=?');$st->execute([$edit]);$editing=$st->fetch()?:null;}
$venues=$pdo->query('SELECT v.*,COUNT(DISTINCT s.seat_id) seat_count,COUNT(DISTINCT sh.show_id) show_count FROM venues v LEFT JOIN seats s ON s.venue_id=v.venue_id LEFT JOIN shows sh ON sh.venue_id=v.venue_id GROUP BY v.venue_id ORDER BY v.name')->fetchAll();$pageTitle='Manage venues';require_once __DIR__.'/../../app/includes/header.php';
?>
<section class="featured-events"><div class="section-heading"><p class="eyebrow"><a href="/admin/">ADMIN</a></p><h1>Venues</h1><p>New venues need a seat layout before shows can be scheduled. Use one Section,Row,SeatCount line per row.</p></div>
<?php if($error!==''):?><p class="form-message form-message--error" role="alert"><?=e($error)?></p><?php elseif(isset($_GET['saved'])):?><p class="form-message">Venue saved.</p><?php endif;?>
<form class="auth-form" method="post"><h2><?=$editing?'Edit venue details':'Add venue and seat layout'?></h2><input type="hidden" name="_csrf" value="<?=e(csrfToken())?>"><input type="hidden" name="venue_id" value="<?=(int)($editing['venue_id']??0)?>"><label for="name">Venue name</label><input id="name" name="name" maxlength="160" required value="<?=e((string)($editing['name']??''))?>"><label for="address">Address</label><textarea id="address" name="address" maxlength="500" rows="2"><?=e((string)($editing['address']??''))?></textarea><?php if(!$editing):?><label for="layout">Seat rows</label><textarea id="layout" name="layout" rows="5" required placeholder="Stalls,A,10&#10;Stalls,B,10&#10;Balcony,A,8"></textarea><p class="form-message">Seat numbers start at 1 for each row.</p><?php endif;?><button class="btn" type="submit">Save venue</button></form>
<div class="event-grid"><?php foreach($venues as $v):?><article class="event-card"><div class="event-card-content"><span class="event-category"><?=(int)$v['seat_count']?> seats · <?=(int)$v['show_count']?> shows</span><h2><?=e($v['name'])?></h2><p><?=e($v['address']??'')?></p><a class="btn" href="?edit=<?=(int)$v['venue_id']?>">Edit details</a></div></article><?php endforeach;?></div></section>
<?php require_once __DIR__.'/../../app/includes/footer.php'; ?>

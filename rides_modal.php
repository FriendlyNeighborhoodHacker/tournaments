<?php
// rides_modal.php
// Reusable admin rides modal partial.
// Expects variables to be defined by the includer:
// - $modalId (string) unique DOM id, e.g. 'ridesModal_123'
// - $modalTitle (string) tournament name
// - $tournamentId (int)
// - $ref (string) URL to redirect back after save
// - $members (array) rows: ['user_id','first_name','last_name','has_ride']

require_once __DIR__.'/partials.php';
?>
<div id="<?=h($modalId)?>" class="modal hidden" aria-hidden="true">
  <div class="modal-content">
    <button class="close" onclick="closeAdminRidesModal('<?=h($modalId)?>')">×</button>
    <h3>Rides — <?=h($modalTitle)?></h3>
    <form method="post" action="/ride_actions.php" class="stack">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <input type="hidden" name="action" value="admin_bulk_set">
      <input type="hidden" name="tournament_id" value="<?=h($tournamentId)?>">
      <input type="hidden" name="ref" value="<?=h($ref)?>">
      <table class="list">
        <thead><tr><th>Name</th><th>Yes</th><th>No</th><th>Unspecified</th></tr></thead>
        <tbody>
        <?php foreach($members as $m): ?>
          <tr>
            <td><?=h($m['last_name'].', '.$m['first_name'])?></td>
            <td><input type="radio" name="ride[<?=$m['user_id']?>]" value="1" <?= ($m['has_ride']==='1'||$m['has_ride']===1)?'checked':'' ?>></td>
            <td><input type="radio" name="ride[<?=$m['user_id']?>]" value="0" <?= ($m['has_ride']==='0'||$m['has_ride']===0)?'checked':'' ?>></td>
            <td><input type="radio" name="ride[<?=$m['user_id']?>]" value="" <?= ($m['has_ride']===null)?'checked':'' ?>></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="actions">
        <button class="primary">Save</button>
        <button type="button" onclick="closeAdminRidesModal('<?=h($modalId)?>')">Cancel</button>
      </div>
    </form>
  </div>
</div>

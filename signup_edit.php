<?php
require_once __DIR__.'/partials.php';
require_once __DIR__.'/lib/Signups.php';
require_admin();

$pdo = pdo();
$err = null;

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM signups WHERE id=?");
$st->execute([$id]);
$signup = $st->fetch();
if (!$signup) { http_response_code(404); exit('Not found'); }

if ($_SERVER['REQUEST_METHOD']==='POST') {
  require_csrf();
  $go_mav = !empty($_POST['go_maverick']) ? 1 : 0;
  $comment = trim($_POST['comment'] ?? '');
  $member_ids = array_map('intval', $_POST['member_ids'] ?? []);
  $member_ids = array_values(array_unique($member_ids));
  // Disallow coaches in teams
  if (empty($err) && !empty($member_ids)) {
    $in = implode(',', array_fill(0, count($member_ids), '?'));
    $stc = $pdo->prepare("SELECT first_name,last_name FROM users WHERE is_coach=1 AND id IN ($in)");
    $stc->execute($member_ids);
    $coaches = $stc->fetchAll();
    if ($coaches) {
      $err = 'Coaches cannot be added to teams: '.implode(', ', array_map(fn($r)=>$r['first_name'].' '.$r['last_name'], $coaches));
    }
  }
  
  // Use Signups class to replace the team (handles all validation and database operations)
  if (empty($err)) {
    try {
      Signups::replaceTeam($id, $signup['tournament_id'], $member_ids, (bool)$go_mav, $comment);
      header('Location: /upcoming_tournaments.php'); exit;
    } catch (DomainException $e) {
      $err = $e->getMessage();
    } catch (Throwable $e) {
      $err = 'Failed to save changes.';
    }
  }
}

$roster = $pdo->query("SELECT id,first_name,last_name,is_coach,is_admin FROM users ORDER BY last_name,first_name")->fetchAll();
$members = $pdo->prepare("SELECT user_id FROM signup_members WHERE signup_id=?");
$members->execute([$id]);
$currentMembers = array_column($members->fetchAll(), 'user_id');

// Get member details for pre-population
$memberDetails = [];
if (!empty($currentMembers)) {
  $placeholders = implode(',', array_fill(0, count($currentMembers), '?'));
  $memberSt = $pdo->prepare("SELECT id, first_name, last_name, is_admin FROM users WHERE id IN ($placeholders)");
  $memberSt->execute($currentMembers);
  $memberDetails = $memberSt->fetchAll();
}

// Get tournament details for team size max
$tournamentSt = $pdo->prepare("SELECT name, team_size_max FROM tournaments WHERE id=?");
$tournamentSt->execute([$signup['tournament_id']]);
$tournament = $tournamentSt->fetch();

header_html('Edit Signup');
?>
<h2>Edit Signup</h2>
<?php if(!empty($err)):?><p class="error"><?=h($err)?></p><?php endif; ?>
<p><strong>Tournament:</strong> <?=h($tournament['name'])?></p>

<form id="editSignupForm" method="post" class="stack" onsubmit="return validateEditForm()">
  <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
  
  <p class="small" id="editHelperText" style="margin:8px 0 16px;color:#555;">You are an admin, you can add teams that you are not on. If you are part of a team that you are adding, make sure to add yourself.</p>
  
  <div id="editTeamSelectionWrap">
    <label>Search for team members
      <input type="text" id="edit_search_input" placeholder="Type to search..." autocomplete="off">
    </label>
    <div id="edit_search_results" class="search-results hidden"></div>
    
    <div id="edit_selected_members" class="selected-members">
      <div id="edit_members_list"></div>
      <small class="small" id="editMembersCount"></small>
    </div>
  </div>
  
  <div id="editMaverickWrap" class="<?= empty($currentMembers) ? '' : 'hidden' ?>">
    <label><input type="checkbox" id="edit_go_maverick" name="go_maverick" value="1" <?= $signup['go_maverick']?'checked':'' ?>> Go Maverick (I want to compete solo)</label>
  </div>
  
  <label>Comment (optional)
    <textarea name="comment" rows="3" maxlength="500"><?=h($signup['comment'])?></textarea>
  </label>
  <div class="actions">
    <button type="submit" class="primary">Save</button>
    <a class="button" href="/upcoming_tournaments.php">Cancel</a>
  </div>
</form>

<script>
// Edit page state
let editState = {
  selectedMembers: [],
  searchTimeout: null,
  teamSizeMax: <?= json_encode($tournament['team_size_max']) ?>,
  tournamentId: <?= json_encode($signup['tournament_id']) ?>
};

// Pre-populate with existing members
window.addEventListener('DOMContentLoaded', () => {
  const existingMembers = <?= json_encode(array_map(function($m) {
    return [
      'id' => (int)$m['id'],
      'first_name' => $m['first_name'],
      'last_name' => $m['last_name'],
      'is_admin' => (bool)$m['is_admin'],
      'display' => $m['last_name'] . ', ' . $m['first_name'] . ($m['is_admin'] ? ' (Admin)' : '')
    ];
  }, $memberDetails)) ?>;
  
  editState.selectedMembers = existingMembers;
  updateEditSelectedMembersDisplay();
  updateEditMaverickVisibility();
  updateEditHelperText();
  
  // Set up search input listener
  const searchInput = document.getElementById('edit_search_input');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      if (editState.searchTimeout) {
        clearTimeout(editState.searchTimeout);
      }
      const query = e.target.value;
      editState.searchTimeout = setTimeout(() => {
        performEditSearch(query);
      }, 300);
    });
    
    // Close results when clicking outside
    document.addEventListener('click', (e) => {
      const resultsEl = document.getElementById('edit_search_results');
      const searchInput = document.getElementById('edit_search_input');
      if (resultsEl && searchInput && !resultsEl.contains(e.target) && e.target !== searchInput) {
        resultsEl.classList.add('hidden');
      }
    });
  }
});

function updateEditSelectedMembersDisplay() {
  const listEl = document.getElementById('edit_members_list');
  const countEl = document.getElementById('editMembersCount');
  
  if (editState.selectedMembers.length === 0) {
    listEl.innerHTML = '<p class="small" style="color:#666;font-style:italic;">No team members selected yet</p>';
    countEl.textContent = '';
  } else {
    listEl.innerHTML = editState.selectedMembers.map(member => `
      <div class="member-chip" data-user-id="${member.id}">
        <span>${member.display}</span>
        <button type="button" class="remove-member" onclick="removeEditMember(${member.id})" aria-label="Remove ${member.first_name} ${member.last_name}">×</button>
        <input type="hidden" name="member_ids[]" value="${member.id}">
      </div>
    `).join('');
    
    const maxSize = editState.teamSizeMax || 3;
    const currentCount = editState.selectedMembers.length;
    countEl.textContent = `${currentCount}/${maxSize} team members selected`;
  }
}

function updateEditMaverickVisibility() {
  const maverickWrap = document.getElementById('editMaverickWrap');
  if (editState.selectedMembers.length === 0) {
    maverickWrap.classList.remove('hidden');
  } else {
    maverickWrap.classList.add('hidden');
  }
}

function updateEditHelperText() {
  const helpEl = document.getElementById('editHelperText');
  const maxSize = editState.teamSizeMax || 3;
  const currentCount = editState.selectedMembers.length;
  
  if (currentCount === 0) {
    helpEl.textContent = `You are an admin, you can add teams that you are not on. If you are part of a team that you are adding, make sure to add yourself.`;
  } else {
    const remaining = maxSize - currentCount;
    if (remaining > 0) {
      helpEl.textContent = `Selected ${currentCount} member${currentCount !== 1 ? 's' : ''}. Can add ${remaining} more (${maxSize} total max).`;
    } else {
      helpEl.textContent = `Team is full (${maxSize} members max).`;
    }
  }
}

function isEditTeamFull() {
  const maxSize = editState.teamSizeMax || 3;
  const currentCount = editState.selectedMembers.length;
  return currentCount >= maxSize;
}

function addEditMember(member) {
  if (editState.selectedMembers.find(m => m.id === member.id)) {
    return;
  }
  
  if (isEditTeamFull()) {
    return;
  }
  
  editState.selectedMembers.push(member);
  updateEditSelectedMembersDisplay();
  updateEditMaverickVisibility();
  updateEditHelperText();
  
  document.getElementById('edit_search_input').value = '';
  document.getElementById('edit_search_results').innerHTML = '';
  document.getElementById('edit_search_results').classList.add('hidden');
  document.getElementById('edit_search_input').disabled = isEditTeamFull();
}

function removeEditMember(userId) {
  editState.selectedMembers = editState.selectedMembers.filter(m => m.id !== userId);
  updateEditSelectedMembersDisplay();
  updateEditMaverickVisibility();
  updateEditHelperText();
  document.getElementById('edit_search_input').disabled = false;
}

function performEditSearch(query) {
  if (!query || query.trim() === '') {
    document.getElementById('edit_search_results').innerHTML = '';
    document.getElementById('edit_search_results').classList.add('hidden');
    return;
  }
  
  const excludeIds = editState.selectedMembers.map(m => m.id);
  const params = new URLSearchParams();
  params.append('q', query.trim());
  excludeIds.forEach(id => params.append('exclude[]', id));
  
  fetch(`/search_users.php?${params.toString()}`)
    .then(response => response.json())
    .then(results => {
      const resultsEl = document.getElementById('edit_search_results');
      
      if (results.length === 0) {
        resultsEl.innerHTML = '<div class="search-result-item no-results">No matches found</div>';
        resultsEl.classList.remove('hidden');
      } else {
        resultsEl.innerHTML = results.map(user => `
          <div class="search-result-item" onclick="addEditMember(${JSON.stringify(user).replace(/"/g, '&quot;')})">
            ${user.display}
          </div>
        `).join('');
        resultsEl.classList.remove('hidden');
      }
    })
    .catch(err => {
      console.error('Search error:', err);
      document.getElementById('edit_search_results').innerHTML = '<div class="search-result-item no-results">Search error</div>';
      document.getElementById('edit_search_results').classList.remove('hidden');
    });
}

function validateEditForm() {
  const goMaverick = document.getElementById('edit_go_maverick').checked;
  const memberCount = editState.selectedMembers.length;
  const maxSize = editState.teamSizeMax || 3;
  
  if (!goMaverick) {
    if (memberCount < 2) {
      alert('Please add at least 2 team members, or check "Go Maverick" to compete solo.');
      return false;
    }
    
    if (memberCount > maxSize) {
      alert(`Team size cannot exceed ${maxSize} members.`);
      return false;
    }
  }
  
  return true;
}
</script>

<?php footer_html(); ?>

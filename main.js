// Signup Modal State
let signupState = {
  selectedMembers: [],
  searchTimeout: null,
  teamSizeMax: null
};

function openSignupModal(t) {
  // Reset state
  signupState.selectedMembers = [];
  signupState.searchTimeout = null;
  
  // Set tournament info
  document.getElementById('m_tournament_id').value = t.tournament_id;
  document.getElementById('modalTitle').textContent = `Sign up — ${t.tournament_name}`;
  
  // Get tournament's team size max
  const tournament = window.APP.tournaments_by_id?.[t.tournament_id];
  signupState.teamSizeMax = tournament?.team_size_max || null;
  document.getElementById('m_team_size_max').value = signupState.teamSizeMax || '';
  
  // Clear search and results
  document.getElementById('m_search_input').value = '';
  document.getElementById('m_search_results').innerHTML = '';
  document.getElementById('m_search_results').classList.add('hidden');
  
  // Reset maverick checkbox
  document.getElementById('m_go_maverick').checked = false;
  
  // Update UI
  updateSelectedMembersDisplay();
  updateMaverickVisibility();
  updateHelperText();
  
  // Show modal
  document.getElementById('signupModal').classList.remove('hidden');
  document.getElementById('signupModal').setAttribute('aria-hidden', 'false');
  
  // Focus search input
  setTimeout(() => document.getElementById('m_search_input').focus(), 100);
}

function closeSignupModal() {
  const m = document.getElementById('signupModal');
  if (!m) return;
  m.classList.add('hidden');
  m.setAttribute('aria-hidden', 'true');
  
  // Clear search timeout if pending
  if (signupState.searchTimeout) {
    clearTimeout(signupState.searchTimeout);
    signupState.searchTimeout = null;
  }
}

function updateSelectedMembersDisplay() {
  const listEl = document.getElementById('m_members_list');
  const countEl = document.getElementById('membersCount');
  
  if (signupState.selectedMembers.length === 0) {
    listEl.innerHTML = '<p class="small" style="color:#666;font-style:italic;">No team members selected yet</p>';
    countEl.textContent = '';
  } else {
    // Create member chips
    listEl.innerHTML = signupState.selectedMembers.map(member => `
      <div class="member-chip" data-user-id="${member.id}">
        <span>${member.display}</span>
        <button type="button" class="remove-member" onclick="removeMember(${member.id})" aria-label="Remove ${member.first_name} ${member.last_name}">×</button>
        <input type="hidden" name="partner_ids[]" value="${member.id}">
      </div>
    `).join('');
    
    // Update count display
    const isAdmin = !!window.APP.isAdmin;
    const maxSize = signupState.teamSizeMax || (isAdmin ? 3 : 3);
    const currentCount = signupState.selectedMembers.length;
    const totalWithUser = isAdmin ? currentCount : currentCount + 1; // Non-admins auto-include themselves
    
    countEl.textContent = `${totalWithUser}/${maxSize} team members selected`;
  }
}

function updateMaverickVisibility() {
  const maverickWrap = document.getElementById('maverickWrap');
  const teamSelectionWrap = document.getElementById('teamSelectionWrap');
  
  if (signupState.selectedMembers.length === 0) {
    maverickWrap.classList.remove('hidden');
    teamSelectionWrap.style.display = 'block';
  } else {
    maverickWrap.classList.add('hidden');
    teamSelectionWrap.style.display = 'block';
  }
}

function updateHelperText() {
  const helpEl = document.getElementById('partnersHelp');
  const isAdmin = !!window.APP.isAdmin;
  const maxSize = signupState.teamSizeMax || 3;
  const currentCount = signupState.selectedMembers.length;
  
  if (isAdmin) {
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
  } else {
    if (currentCount === 0) {
      helpEl.textContent = `Select the team members you want to go with. (You are automatically part of the team.)`;
    } else {
      const totalWithUser = currentCount + 1;
      const remaining = maxSize - totalWithUser;
      if (remaining > 0) {
        helpEl.textContent = `You plus ${currentCount} partner${currentCount !== 1 ? 's' : ''}. Can add ${remaining} more (${maxSize} total max).`;
      } else {
        helpEl.textContent = `Team is full (you plus ${currentCount} partners = ${maxSize} total).`;
      }
    }
  }
}

function isTeamFull() {
  const isAdmin = !!window.APP.isAdmin;
  const maxSize = signupState.teamSizeMax || 3;
  const currentCount = signupState.selectedMembers.length;
  const totalWithUser = isAdmin ? currentCount : currentCount + 1;
  return totalWithUser >= maxSize;
}

function addMember(member) {
  // Check if already added
  if (signupState.selectedMembers.find(m => m.id === member.id)) {
    return;
  }
  
  // Check if team is full
  if (isTeamFull()) {
    return;
  }
  
  // Add member
  signupState.selectedMembers.push(member);
  
  // Update UI
  updateSelectedMembersDisplay();
  updateMaverickVisibility();
  updateHelperText();
  
  // Clear search
  document.getElementById('m_search_input').value = '';
  document.getElementById('m_search_results').innerHTML = '';
  document.getElementById('m_search_results').classList.add('hidden');
  
  // Disable search input if team is full
  document.getElementById('m_search_input').disabled = isTeamFull();
}

function removeMember(userId) {
  signupState.selectedMembers = signupState.selectedMembers.filter(m => m.id !== userId);
  
  // Update UI
  updateSelectedMembersDisplay();
  updateMaverickVisibility();
  updateHelperText();
  
  // Re-enable search input
  document.getElementById('m_search_input').disabled = false;
}

function performSearch(query) {
  if (!query || query.trim() === '') {
    document.getElementById('m_search_results').innerHTML = '';
    document.getElementById('m_search_results').classList.add('hidden');
    return;
  }
  
  // Get excluded IDs (already selected members)
  const excludeIds = signupState.selectedMembers.map(m => m.id);
  
  // Build query string
  const params = new URLSearchParams();
  params.append('q', query.trim());
  excludeIds.forEach(id => params.append('exclude[]', id));
  
  // Perform AJAX request
  fetch(`/search_users.php?${params.toString()}`)
    .then(response => response.json())
    .then(results => {
      const resultsEl = document.getElementById('m_search_results');
      
      if (results.length === 0) {
        resultsEl.innerHTML = '<div class="search-result-item no-results">No matches found</div>';
        resultsEl.classList.remove('hidden');
      } else {
        resultsEl.innerHTML = results.map(user => `
          <div class="search-result-item" onclick="addMember(${JSON.stringify(user).replace(/"/g, '&quot;')})">
            ${user.display}
          </div>
        `).join('');
        resultsEl.classList.remove('hidden');
      }
    })
    .catch(err => {
      console.error('Search error:', err);
      document.getElementById('m_search_results').innerHTML = '<div class="search-result-item no-results">Search error</div>';
      document.getElementById('m_search_results').classList.remove('hidden');
    });
}

function submitSignupForm(form) {
  const goMaverick = document.getElementById('m_go_maverick').checked;
  const isAdmin = !!window.APP.isAdmin;
  const memberCount = signupState.selectedMembers.length;
  const maxSize = signupState.teamSizeMax || 3;
  const totalWithUser = isAdmin ? memberCount : memberCount + 1;
  
  if (!goMaverick) {
    // Must have at least 2 people total (or up to maxSize)
    if (totalWithUser < 2) {
      alert('Please add at least one team member, or check "Go Maverick" to compete solo.');
      return false;
    }
    
    if (totalWithUser > maxSize) {
      alert(`Team size cannot exceed ${maxSize} members.`);
      return false;
    }
  }
  
  return true; // Allow submission
}

// Event listener for search input (with debouncing)
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('m_search_input');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      // Clear existing timeout
      if (signupState.searchTimeout) {
        clearTimeout(signupState.searchTimeout);
      }
      
      // Set new timeout for debounced search
      const query = e.target.value;
      signupState.searchTimeout = setTimeout(() => {
        performSearch(query);
      }, 300);
    });
    
    // Close results when clicking outside
    document.addEventListener('click', (e) => {
      const resultsEl = document.getElementById('m_search_results');
      const searchInput = document.getElementById('m_search_input');
      if (resultsEl && searchInput && !resultsEl.contains(e.target) && e.target !== searchInput) {
        resultsEl.classList.add('hidden');
      }
    });
  }
});

/* Close modal on backdrop click or Esc (guarded for pages without the modal, resilient under debugger) */
document.addEventListener('keydown', (e)=>{ 
  try {
    const m = document.getElementById('signupModal');
    if (!m) return;
    if (e.key === 'Escape' && !m.classList.contains('hidden')) closeSignupModal();
  } catch (_) { /* ignore */ }
});
document.addEventListener('click', (e)=>{ 
  try {
    const m = document.getElementById('signupModal');
    if (!m) return;
    if (!m.classList.contains('hidden') && e.target === m) closeSignupModal();
  } catch (_) { /* ignore */ }
});

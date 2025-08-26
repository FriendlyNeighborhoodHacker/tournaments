function openSignupModal(t) {
  document.getElementById('m_tournament_id').value = t.tournament_id;
  document.getElementById('modalTitle').textContent = `Sign up — ${t.tournament_name}`;
  populatePartners();
  const isAdmin = !!window.APP.isAdmin;
  document.getElementById('partnersHelp').textContent = isAdmin
    ? 'Pick 1–3 team members.'
    : 'You’ll be included automatically; choose 1–2 partners (2–3 total).';
  const maverick = document.getElementById('m_go_maverick');
  maverick.checked = false;
  toggleMaverick();
  document.getElementById('signupModal').classList.remove('hidden');
  document.getElementById('signupModal').setAttribute('aria-hidden','false');
}
function closeSignupModal() {
  document.getElementById('signupModal').classList.add('hidden');
  document.getElementById('signupModal').setAttribute('aria-hidden','true');
}
function populatePartners() {
  const sel = document.getElementById('m_partners');
  sel.innerHTML = '';
  const me = window.APP.currentUserId;
  const isAdmin = !!window.APP.isAdmin;
  window.APP.roster.forEach(p => {
    if (!isAdmin && p.id === me) return; // members will include me automatically
    const opt = document.createElement('option');
    opt.value = p.id;
    opt.textContent = `${p.last_name}, ${p.first_name}${p.is_coach ? ' (Coach)' : ''}${p.is_admin ? ' (Admin)' : ''}`;
    sel.appendChild(opt);
  });
}
function toggleMaverick() {
  const on = document.getElementById('m_go_maverick').checked;
  const wrap = document.getElementById('partnerWrap');
  const sel = document.getElementById('m_partners');
  wrap.style.display = on ? 'none' : 'block';
  sel.required = !on; // BUGFIX: ensure browser validation doesn't block Maverick submissions
  if (on) [...sel.options].forEach(o => o.selected = false);
}
function submitSignupForm(form) {
  const isAdmin = !!window.APP.isAdmin;
  if (!document.getElementById('m_go_maverick').checked) {
    const sel = document.getElementById('m_partners');
    const chosen = [...sel.options].filter(o => o.selected).length;
    if (isAdmin) {
      if (chosen < 1 || chosen > 3) { alert('Pick 1–3 members.'); return false; }
    } else {
      if (chosen < 1 || chosen > 2) { alert('Choose 1–2 partners (teams are 2–3 total).'); return false; }
    }
  }
  return true; // normal POST
}

// Close modal on backdrop click or Esc
document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') closeSignupModal(); });
document.addEventListener('click', (e)=>{ 
  const m = document.getElementById('signupModal');
  if (!m.classList.contains('hidden') && e.target === m) closeSignupModal();
});

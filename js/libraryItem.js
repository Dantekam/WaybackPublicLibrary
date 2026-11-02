(function () {
  'use strict';
  const form = document.getElementById('libraryItemForm');
  const existing = document.getElementById('existingFields');
  const fresh = document.getElementById('newFields');
  const searchInput = document.getElementById('catalogSearch');
  const results = document.getElementById('catalogResults');
  const details = document.getElementById('selectedCatalogDetails');
  const branchSelect = document.getElementById('branchSelect');
  const branchNote = document.getElementById('branchNote');
  const message = document.getElementById('libraryMessage');
  const save = document.getElementById('saveLibraryBtn');
  let catalogItems = [];
  let centralID = null;
  const today = new Date();
  const localToday = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
  document.getElementById('acquisitionDate').value = localToday;

  function mode() { return form.querySelector('input[name="mode"]:checked').value; }
  function toggleMode() {
    const isNew = mode() === 'new';
    existing.hidden = isNew;
    fresh.hidden = !isNew;
    results.required = !isNew;
    fresh.querySelectorAll('[name="newItemID"],[name="title"],[name="itemType"],[name="publisher"],[name="libraryOfCongressCode"],[name="cost"]').forEach(el => { el.required = isNew; });
    message.textContent = '';
  }
  form.querySelectorAll('input[name="mode"]').forEach(r => r.addEventListener('change', toggleMode));
  toggleMode();

  async function fetchData(query = '') {
    const response = await fetch('script/catalogData.php?q=' + encodeURIComponent(query));
    const json = await response.json();
    if (!response.ok || !json.ok) throw new Error(json.error || 'Could not load data.');
    return json;
  }
  function updateBranchNote() {
    const central = branchSelect.value && branchSelect.value === String(centralID);
    branchNote.textContent = central ? 'Central branch: copy will be Available.' : 'Other branch: copy will be Maintenance (unavailable) until processed.';
  }
  async function loadBranches() {
    try {
      const data = await fetchData();
      branchSelect.replaceChildren();
      centralID = data.branches.find(b => /central/i.test(b.BranchName))?.BranchID ?? null;
      data.branches.forEach(b => {
        const option = new Option(`${b.BranchName} (#${b.BranchID})`, b.BranchID);
        branchSelect.add(option);
      });
      if (centralID !== null) branchSelect.value = String(centralID);
      else { branchSelect.insertBefore(new Option('Select branch', ''), branchSelect.firstChild); branchSelect.value = ''; }
      updateBranchNote();
    } catch (e) { message.textContent = e.message; message.className = 'msg error'; }
  }
  branchSelect.addEventListener('change', updateBranchNote);
  async function search() {
    const q = searchInput.value.trim();
    if (!q) { message.textContent = 'Enter a title or Item ID to search.'; message.className = 'msg error'; return; }
    try {
      const data = await fetchData(q);
      catalogItems = data.items;
      results.replaceChildren(new Option(data.items.length ? 'Select an item' : 'No matching items', ''));
      data.items.forEach(item => results.add(new Option(`#${item.ItemID} — ${item.Title} (${item.ItemType})`, item.ItemID)));
      details.textContent = data.items.length ? `${data.items.length} result(s) found.` : 'No match. Select Create new item above.';
      message.textContent = '';
    } catch (e) { message.textContent = e.message; message.className = 'msg error'; }
  }
  document.getElementById('searchCatalogBtn').addEventListener('click', search);
  searchInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); search(); } });
  results.addEventListener('change', () => {
    const item = catalogItems.find(x => String(x.ItemID) === results.value);
    details.textContent = item ? `Title: ${item.Title} | Type: ${item.ItemType} | Publisher: ${item.Publisher}` : '';
  });
  form.addEventListener('submit', async e => {
    e.preventDefault();
    message.textContent = '';
    message.className = 'msg';
    if (mode() === 'existing' && !results.value) { message.textContent = 'Select an existing catalog item first.'; message.className = 'msg error'; return; }
    save.disabled = true;
    try {
      const response = await fetch('script/saveLibraryItem.php', { method: 'POST', body: new FormData(form) });
      const json = await response.json();
      if (!response.ok || !json.ok) throw new Error(json.error || 'Save failed.');
      message.textContent = `Saved copy #${json.itemCopyID} for ${json.title} (catalog #${json.itemID}). Branch: ${json.branch}. Status: ${json.status}. Acquired: ${json.acquisitionDate}.`;
      message.className = 'msg success';
      form.elements.itemCopyID.value = '';
      form.elements.itemCopyID.focus();
    } catch (err) { message.textContent = err.message; message.className = 'msg error'; }
    finally { save.disabled = false; }
  });
  loadBranches();
})();

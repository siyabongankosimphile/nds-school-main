// This makes the buttons work with the message box
document.getElementById('import-excel-btn').addEventListener('click', function() {
  // 1. Show spinner
  document.getElementById('nds-feedback-box').style.display = 'block';
  
  // 2. Start import
  // ... code to actually do the import ...
  
  // 3. When done, show checkmark
  // 4. If error, show red X
});
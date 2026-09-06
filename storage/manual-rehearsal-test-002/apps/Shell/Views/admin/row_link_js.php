<script>
document.addEventListener('click', function (event) {
  var row = event.target && event.target.closest ? event.target.closest('tr.row-link') : null;
  if (!row) return;
  if (event.target && event.target.closest && event.target.closest('a,button,input,select,textarea,label')) return;

  var href = row.getAttribute('data-row-href');
  if (href) {
    window.location.href = href;
  }
});

document.addEventListener('keydown', function (event) {
  var row = event.target && event.target.matches && event.target.matches('tr.row-link') ? event.target : null;
  if (!row) return;
  if (event.key !== 'Enter' && event.key !== ' ') return;

  event.preventDefault();
  var href = row.getAttribute('data-row-href');
  if (href) {
    window.location.href = href;
  }
});
</script>

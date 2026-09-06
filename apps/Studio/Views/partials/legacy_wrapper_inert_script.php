<script>
(function () {
  var legacyStudio = document.querySelector('.legacy-studio');
  if (!legacyStudio) {
    return;
  }
  legacyStudio.setAttribute('aria-hidden', 'true');
  if ('inert' in legacyStudio) {
    legacyStudio.inert = true;
  }
  Array.prototype.forEach.call(legacyStudio.querySelectorAll('[id]'), function (node) {
    var originalId = String(node.id || '');
    if (originalId === '') {
      return;
    }
    node.setAttribute('data-legacy-id', originalId);
    node.id = originalId + '--legacy';
  });
  Array.prototype.forEach.call(legacyStudio.querySelectorAll('form'), function (legacyForm) {
    legacyForm.setAttribute('data-legacy-form-disabled', '1');
    Array.prototype.forEach.call(legacyForm.elements || [], function (control) {
      if (control && control.tagName !== 'BUTTON') {
        control.disabled = true;
      }
    });
  });
})();
</script>

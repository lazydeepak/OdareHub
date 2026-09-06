  function toggleRuleEffectTarget(effectType) {
    var targetSelect = document.getElementById('rs_effect_target');
    if (!targetSelect) return;
    var options = targetSelect.options;
    if (effectType === 'set_style_token') {
      for (var i = 0; i < options.length; i++) {
        options[i].style.display = (options[i].value === '' || options[i].value.indexOf('--') === 0) ? '' : 'none';
      }
      targetSelect.value = '';
    } else if (effectType === 'show_warning') {
      for (var i = 0; i < options.length; i++) {
        options[i].style.display = (options[i].value === '' || options[i].value === 'preview_header' || options[i].value === 'preview_footer') ? '' : 'none';
      }
      targetSelect.value = '';
    } else if (effectType === 'hide_field') {
      for (var i = 0; i < options.length; i++) {
        options[i].style.display = (options[i].value === '' || (options[i].parentNode.id === 'rs_field_targets')) ? '' : 'none';
      }
      targetSelect.value = '';
    } else {
      for (var i = 0; i < options.length; i++) {
        options[i].style.display = '';
      }
      targetSelect.value = '';
    }
  }

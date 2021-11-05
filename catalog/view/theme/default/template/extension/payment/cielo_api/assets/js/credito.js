$(document).ready(function() {
  setTimeout(function() {
    $('div #warning').each(function() {
      $(this).remove();
    });
  }, 10000);

  $(document).on('contextmenu', function(e) {
    return false;
  });

  $(document).on('cut copy paste', function(e) {
    e.preventDefault();
  });

  $('#payment input[type="radio"][name="bandeira"]').change(function() {
    parcelas($(this).val());
  });

  $('#payment input[type="text"][name="cartao"]').on('keyup change', function() {
    $(this).val($(this).val().replace(/[^\d]/g,''));
  });

  $('#payment input[type="text"][name="codigo"]').on('keyup change', function() {
    $(this).val($(this).val().replace(/[^\d]/g,''));
  });

  $('#payment input[type="text"], #payment select').blur(function() {
    $('.text-danger').remove();
    $(this).removeClass('alert-danger');
  });

  $('#button-confirm').on('click', function(e) {
    e.preventDefault();
    validar();
  });
});
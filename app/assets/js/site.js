$(function () {
  // client-side filter box on category/addon/unsorted listing pages
  var $filter = $('#addon-filter');
  if ($filter.length) {
    $filter.on('input', function () {
      var q = $(this).val().toLowerCase().trim();
      $('.addon-card').each(function () {
        var $card = $(this);
        var matches = !q || $card.data('name').indexOf(q) !== -1 || $card.data('desc').indexOf(q) !== -1;
        $card.toggleClass('is-hidden', !matches);
      });
      $('.category-section').each(function () {
        var $section = $(this);
        var visible = $section.find('.addon-card').not('.is-hidden').length;
        $section.toggle(visible > 0);
      });
    });
  }

  // admin categorize/type AJAX form
  $('#admin-table').on('click', '.admin-row__save', function () {
    var $row = $(this).closest('.admin-row');
    var repoId = $row.data('repo-id');
    var type = $row.find('.admin-row__type').val();
    var categoryIds = $row.find('.admin-row__categories').val() || [];
    var $status = $row.find('.admin-row__status');

    $row.removeClass('is-saved is-error');
    $status.text('Saving…');

    $.ajax({
      url: '/admin/repos/' + repoId,
      method: 'POST',
      data: {
        type: type,
        category_ids: categoryIds
      },
      dataType: 'json'
    }).done(function (res) {
      $row.addClass('is-saved');
      $status.text('Saved ✓');
      if (type !== 'Unsorted' && type !== 'Incomplete') {
        $row.fadeOut(300, function () { $row.remove(); });
      }
    }).fail(function (xhr) {
      $row.addClass('is-error');
      var msg = 'Save failed';
      try {
        var body = JSON.parse(xhr.responseText);
        if (body.error) msg = [].concat(body.error).join(', ');
      } catch (e) {}
      $status.text(msg);
    });
  });
});

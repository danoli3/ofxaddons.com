$(function () {
  // client-side filter box on category/addon/unsorted listing pages -
  // works across all currently-loaded cards, including ones appended
  // later by infinite scroll
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

  // infinite scroll: each addon-grid ships with a sentinel element right
  // after it. When the sentinel scrolls into view and there's more to
  // load, fetch the next page (server renders just the card fragment
  // for AJAX requests) and append it.
  function incrementPage(url) {
    var u = new URL(url, window.location.origin);
    var page = parseInt(u.searchParams.get('page') || '1', 10);
    u.searchParams.set('page', page + 1);
    return u.pathname + u.search;
  }

  $('.grid-sentinel').each(function () {
    var $sentinel = $(this);
    var $grid = $sentinel.prev('.addon-grid');
    var $loading = $sentinel.next('.grid-loading');
    var $end = $loading.next('.grid-end');
    if (!$grid.length) return;

    var loading = false;

    function loadMore() {
      if (loading || $grid.data('has-more') != 1) return;
      loading = true;
      $loading.prop('hidden', false);

      $.ajax({
        url: $grid.data('next-url'),
        method: 'GET'
      }).done(function (html, status, xhr) {
        $grid.append(html);
        var hasMore = xhr.getResponseHeader('X-Has-More') === '1';
        $grid.attr('data-has-more', hasMore ? '1' : '0');
        $grid.attr('data-next-url', incrementPage($grid.data('next-url')));
        $loading.prop('hidden', true);
        if (!hasMore) {
          $end.prop('hidden', false);
          observer.disconnect();
        }
        // re-apply any active filter to the newly-appended cards
        $('#addon-filter').trigger('input');
      }).fail(function () {
        $loading.prop('hidden', true);
      }).always(function () {
        loading = false;
      });
    }

    var observer = new IntersectionObserver(function (entries) {
      if (entries[0].isIntersecting) loadMore();
    }, { rootMargin: '400px' });
    observer.observe(this);
  });

  // admin categorize/type AJAX - shared by Save, Ban, and Unban, which
  // all just post a type (+ optional category_ids) to the same endpoint
  function saveRepoType($row, type, categoryIds, removeIfNot) {
    var repoId = $row.data('repo-id');
    var $status = $row.find('.admin-row__status');

    $row.removeClass('is-saved is-error');
    $status.text('Saving…');

    $.ajax({
      url: '/admin/repos/' + repoId,
      method: 'POST',
      data: {
        type: type,
        category_ids: categoryIds || []
      },
      dataType: 'json'
    }).done(function () {
      $row.addClass('is-saved');
      $status.text('Saved ✓');
      if (removeIfNot && removeIfNot.indexOf(type) === -1) {
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
  }

  $('#admin-table').on('click', '.admin-row__save', function () {
    var $row = $(this).closest('.admin-row');
    var type = $row.find('.admin-row__type').val();
    var categoryIds = $row.find('.admin-row__categories').val();
    saveRepoType($row, type, categoryIds, ['Unsorted', 'Incomplete']);
  });

  $('#admin-table').on('click', '.admin-row__ban', function () {
    var $row = $(this).closest('.admin-row');
    saveRepoType($row, 'NonAddon', [], []);
  });

  $('#admin-table').on('click', '.admin-row__unban', function () {
    var $row = $(this).closest('.admin-row');
    saveRepoType($row, 'Unsorted', [], []);
  });
});

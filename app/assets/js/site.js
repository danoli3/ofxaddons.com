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
  // all just post a type (+ optional category_ids/description) to the
  // same endpoint. description is omitted entirely for Ban/Unban so it
  // doesn't get clobbered - the backend only touches it when the key
  // is present at all.
  function saveRepoType($row, type, categoryIds, removeIfNot, description) {
    var repoId = $row.data('repo-id');
    var $status = $row.find('.admin-row__status');

    $row.removeClass('is-saved is-error');
    $status.text('Saving…');

    var data = {
      type: type,
      category_ids: categoryIds || []
    };
    if (description !== undefined) data.description = description;

    $.ajax({
      url: '/admin/repos/' + repoId,
      method: 'POST',
      data: data,
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
    var description = $row.find('.admin-row__desc').val();
    saveRepoType($row, type, categoryIds, ['Unsorted', 'Incomplete'], description);
  });

  $('#admin-table').on('click', '.admin-row__ban', function () {
    var $row = $(this).closest('.admin-row');
    saveRepoType($row, 'NonAddon', [], []);
  });

  $('#admin-table').on('click', '.admin-row__unban', function () {
    var $row = $(this).closest('.admin-row');
    saveRepoType($row, 'Unsorted', [], []);
  });

  $('#admin-table').on('click', '.admin-row__generate-desc', function () {
    var $btn = $(this);
    var $row = $btn.closest('.admin-row');
    var repoId = $row.data('repo-id');
    var $desc = $row.find('.admin-row__desc');
    var $status = $row.find('.admin-row__status');

    $btn.prop('disabled', true);
    $status.text('Generating…');

    $.ajax({
      url: '/admin/repos/' + repoId + '/generate-description',
      method: 'POST',
      dataType: 'json'
    }).done(function (res) {
      $desc.val(res.description);
      $status.text('Suggested - review & Save');
    }).fail(function (xhr) {
      var msg = 'Generate failed';
      try {
        var body = JSON.parse(xhr.responseText);
        if (body.error) msg = [].concat(body.error).join(', ');
      } catch (e) {}
      $status.text(msg);
    }).always(function () {
      $btn.prop('disabled', false);
    });
  });

  // admin table: paginated (same shape as the public addon-grid, but
  // rows go into a <tbody> instead of a <div>) plus AJAX tab switching
  // between Unsorted/Incomplete instead of a full page load - the
  // table used to render every Unsorted+Incomplete repo (thousands of
  // rows, each with a <select multiple>) in one page, which made the
  // whole page unresponsive to clicks.
  var $adminTbody = $('#admin-tbody');
  if ($adminTbody.length) {
    var $adminSentinel = $('#admin-sentinel');
    var $adminLoading = $adminSentinel.next('.grid-loading');
    var $adminEnd = $adminLoading.next('.grid-end');
    var adminLoading = false;

    function loadAdminRows(url, replace) {
      if (adminLoading) return;
      adminLoading = true;
      $adminLoading.prop('hidden', false);
      if (replace) $adminEnd.prop('hidden', true);

      $.ajax({ url: url, method: 'GET' }).done(function (html, status, xhr) {
        if (replace) $adminTbody.empty();
        $adminTbody.append(html);
        var hasMore = xhr.getResponseHeader('X-Has-More') === '1';
        $adminTbody.attr('data-has-more', hasMore ? '1' : '0');
        $adminTbody.attr('data-next-url', incrementPage(url));
        $adminLoading.prop('hidden', true);
        $adminEnd.prop('hidden', !!hasMore);
      }).fail(function () {
        $adminLoading.prop('hidden', true);
      }).always(function () {
        adminLoading = false;
      });
    }

    var adminObserver = new IntersectionObserver(function (entries) {
      if (entries[0].isIntersecting && $adminTbody.data('has-more') == 1) {
        loadAdminRows($adminTbody.data('next-url'), false);
      }
    }, { rootMargin: '400px' });
    adminObserver.observe($adminSentinel[0]);

    $('.admin-tab').on('click', function (e) {
      e.preventDefault();
      var url = $(this).attr('href');
      $('.admin-tab').removeClass('active');
      $(this).addClass('active');
      if (window.history && history.pushState) history.pushState(null, '', url);
      var sep = url.indexOf('?') === -1 ? '?' : '&';
      loadAdminRows(url + sep + 'page=1', true);
    });
  }
});

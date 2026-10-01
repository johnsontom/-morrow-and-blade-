/* Search and in-page paging for the owner's team and login lists.
   Every [data-list-group] keeps its own page, so each branch's people page
   independently of the others. Nothing reloads and no second page is opened. */
(function () {
  'use strict';

  var DEFAULT_SIZE = 10;

  function Group(root) {
    this.root = root;
    this.items = Array.prototype.slice.call(root.querySelectorAll('[data-list-item]'));
    this.pager = root.querySelector('[data-list-pager]');
    this.status = root.querySelector('[data-page-status]');
    this.empty = root.querySelector('[data-list-empty]');
    this.prev = root.querySelector('[data-page-prev]');
    this.next = root.querySelector('[data-page-next]');
    this.size = parseInt(root.getAttribute('data-page-size'), 10) || DEFAULT_SIZE;
    this.page = 1;
    this.query = '';
    this.count = this.items.length;

    var self = this;

    if (this.prev) {
      this.prev.addEventListener('click', function () { self.show(self.page - 1); });
    }

    if (this.next) {
      this.next.addEventListener('click', function () { self.show(self.page + 1); });
    }

    this.render();
  }

  /* Everything whose searchable text contains the current term. */
  Group.prototype.matches = function () {
    if (this.query === '') {
      return this.items;
    }

    return this.items.filter(function (item) {
      return (item.getAttribute('data-search') || '').toLowerCase().indexOf(this.query) !== -1;
    }, this);
  };

  Group.prototype.search = function (value) {
    this.query = String(value || '').trim().toLowerCase();
    this.page = 1;
    this.render();
  };

  Group.prototype.show = function (page) {
    this.page = page;
    this.render();
  };

  Group.prototype.render = function () {
    var matched = this.matches();
    var pages = Math.max(1, Math.ceil(matched.length / this.size));
    var page = Math.min(Math.max(1, this.page), pages);
    var start = (page - 1) * this.size;
    var visible = matched.slice(start, start + this.size);

    this.page = page;
    this.count = matched.length;

    this.items.forEach(function (item) { item.hidden = true; });
    visible.forEach(function (item) { item.hidden = false; });

    if (this.empty) {
      this.empty.hidden = matched.length !== 0;
    }

    if (this.pager) {
      this.pager.hidden = matched.length <= this.size;
    }

    if (this.status) {
      this.status.textContent = matched.length === 0
        ? ''
        : 'Showing ' + (start + 1) + '\u2013' + (start + visible.length) + ' of ' + matched.length
          + (pages > 1 ? ' \u00b7 page ' + page + ' of ' + pages : '');
    }

    if (this.prev) { this.prev.disabled = page <= 1; }
    if (this.next) { this.next.disabled = page >= pages; }
  };

  /* One search box per section, feeding every group inside it. */
  Array.prototype.slice.call(document.querySelectorAll('[data-list-search]')).forEach(function (input) {
    var scope = document.querySelector('[data-list-scope="' + input.getAttribute('data-list-search') + '"]');

    if (!scope) {
      return;
    }

    var groups = Array.prototype.slice.call(scope.querySelectorAll('[data-list-group]')).map(function (root) {
      return new Group(root);
    });

    // With several groups a branch that matches nobody steps aside; with one
    // group the list itself carries the "nothing found" message.
    var hideEmptyGroups = groups.length > 1;
    var noneFound = scope.querySelector('[data-scope-empty]');

    function apply() {
      var term = input.value.trim();
      var showing = 0;

      groups.forEach(function (group) {
        group.search(term);
        group.root.hidden = hideEmptyGroups && term !== '' && group.count === 0;

        if (group.count > 0) {
          showing += group.count;
        }
      });

      if (noneFound) {
        noneFound.hidden = showing !== 0;
      }
    }

    input.addEventListener('input', apply);
    input.addEventListener('search', apply);
  });

}());

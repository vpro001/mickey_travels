// Small touches used on every page. Everything still works and reads fine without this file.

var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// ---------- Sections fade in as they scroll into view ----------
var revealItems = document.querySelectorAll('.reveal');
if ('IntersectionObserver' in window && !reduceMotion) {
  var revealer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        revealer.unobserve(entry.target);
      }
    });
  }, { rootMargin: '0px 0px -10% 0px' });
  revealItems.forEach(function (el) { revealer.observe(el); });
  // Safety net: never leave anything hidden if the observer doesn't fire
  setTimeout(function () { revealItems.forEach(function (el) { el.classList.add('is-visible'); }); }, 4000);
} else {
  revealItems.forEach(function (el) { el.classList.add('is-visible'); });
}

// ---------- Departures board: letters flip into place like an airport board ----------
var board = document.getElementById('departures');
if (board) {
  var letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

  // Turn each word on the board into separate letter tiles
  board.querySelectorAll('[data-flap]').forEach(function (el) {
    var text = el.textContent.trim();
    el.setAttribute('aria-label', text);   // screen readers read the word, not the tiles
    el.textContent = '';
    text.split('').forEach(function (ch) {
      var tile = document.createElement('span');
      tile.className = 'flap';
      tile.setAttribute('aria-hidden', 'true');
      tile.dataset.final = ch;
      tile.textContent = ch;   // start readable; the animation scrambles and settles it
      el.appendChild(tile);
    });
  });

  function runBoard() {
    var tiles = board.querySelectorAll('.flap');
    tiles.forEach(function (tile, i) {
      var flips = 4 + Math.floor(Math.random() * 8) + Math.floor(i / 12);
      var count = 0;
      var timer = setInterval(function () {
        count++;
        if (count >= flips || tile.dataset.final === ' ') {
          tile.textContent = tile.dataset.final;
          clearInterval(timer);
        } else {
          tile.textContent = letters[Math.floor(Math.random() * letters.length)];
        }
        tile.classList.remove('flip');
        void tile.offsetWidth;   // restart the flip animation
        tile.classList.add('flip');
      }, 70);
    });
  }

  if (!reduceMotion && 'IntersectionObserver' in window) {
    var boardWatcher = new IntersectionObserver(function (entries) {
      if (entries[0].isIntersecting) {
        runBoard();
        boardWatcher.disconnect();
      }
    }, { threshold: 0.3 });
    boardWatcher.observe(board);
  }

  // Live Lagos time on the board (Lagos is UTC+1 all year)
  var clock = document.getElementById('lagos-time');
  if (clock) {
    var showTime = function () {
      clock.textContent = new Date().toLocaleTimeString('en-GB', { timeZone: 'Africa/Lagos', hour: '2-digit', minute: '2-digit' });
    };
    showTime();
    setInterval(showTime, 15000);
  }
}

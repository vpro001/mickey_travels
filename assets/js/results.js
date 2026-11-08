// Flight results: loads the flights, then filters and sorts them without reloading the page.

var results = document.getElementById('results');
var allFlights = [];
var sortBy = 'price';
var lowest = { price: 0, minutes: 0 };

function naira(kobo) {
  return '₦' + Math.round(kobo / 100).toLocaleString('en-NG');
}

function hoursAndMinutes(minutes) {
  var h = Math.floor(minutes / 60), m = minutes % 60;
  return h ? h + 'h ' + (m < 10 ? '0' : '') + m + 'm' : m + 'm';
}

function showMessage(title, text) {
  results.innerHTML = '';
  document.getElementById('message-title').textContent = title;
  document.getElementById('message-text').textContent = text;
  document.getElementById('message').classList.remove('hidden');
  document.getElementById('result-count').textContent = '';
}

// ---------- Load the flights ----------
fetch(results.dataset.url)
  .then(function (response) { return response.json(); })
  .then(function (data) {
    if (!data.ok) {
      showMessage("We couldn't search right now", data.message);
      return;
    }
    if (data.count === 0) {
      showMessage('No flights found', data.message);
      return;
    }

    results.innerHTML = data.html;
    allFlights = Array.from(results.querySelectorAll('.flight'));

    // Lowest price and shortest time, for the sort buttons and "best value"
    lowest.price = data.min_price;
    lowest.minutes = Math.min.apply(null, allFlights.map(function (f) { return +f.dataset.minutes; }));
    document.getElementById('sort-price').textContent = 'from ' + naira(lowest.price);
    document.getElementById('sort-minutes').textContent = 'from ' + hoursAndMinutes(lowest.minutes);

    // Airline checkboxes, with each airline's cheapest price
    var box = document.getElementById('airline-filters');
    data.airlines.forEach(function (a) {
      var label = document.createElement('label');
      label.className = 'flex items-center gap-2';
      label.innerHTML = '<input type="checkbox" name="airline" class="size-4 accent-ink-900"><span class="flex-1"></span><span class="text-xs text-ink-600 num"></span>';
      label.querySelector('input').value = a.code;
      label.children[1].textContent = a.name;
      label.children[2].textContent = a.price_text;
      box.appendChild(label);
    });

    // Price slider from cheapest to dearest
    // (the range is a whole number of steps, so the top of the slider includes the dearest flight)
    var slider = document.getElementById('max-price');
    var step = Math.max(1, Math.ceil((data.max_price - data.min_price) / 100));
    slider.min = data.min_price;
    slider.max = data.min_price + step * 100;
    slider.step = step;
    slider.value = slider.max;

    startTimer(data.expires_at);
    applyFilters();
  })
  .catch(function () {
    showMessage("We couldn't search right now", 'Please check your internet connection and try again.');
  });

// ---------- Filters ----------
function applyFilters() {
  var maxStops = +document.querySelector('input[name="stops"]:checked').value;
  var maxPrice = +document.getElementById('max-price').value;
  var times = Array.from(document.querySelectorAll('input[name="time"]:checked')).map(function (c) { return c.value.split('-'); });
  var airlines = Array.from(document.querySelectorAll('input[name="airline"]:checked')).map(function (c) { return c.value; });

  document.getElementById('max-price-text').textContent = 'Up to ' + naira(maxPrice);

  var shown = 0;
  allFlights.forEach(function (f) {
    var hour = +f.dataset.hour;
    var ok = +f.dataset.stops <= maxStops
      && +f.dataset.price <= maxPrice
      && (airlines.length === 0 || airlines.indexOf(f.dataset.airline) !== -1)
      && (times.length === 0 || times.some(function (t) { return hour >= +t[0] && hour < +t[1]; }));
    f.hidden = !ok;
    if (ok) shown++;
  });

  document.getElementById('result-count').textContent = shown === allFlights.length
    ? allFlights.length + ' flights found'
    : 'Showing ' + shown + ' of ' + allFlights.length + ' flights';
  document.getElementById('no-match').classList.toggle('hidden', shown > 0);
  sortFlights();
}

document.getElementById('filters').addEventListener('change', applyFilters);
document.getElementById('max-price').addEventListener('input', applyFilters);

document.getElementById('reset-filters').addEventListener('click', function () {
  document.querySelector('input[name="stops"][value="9"]').checked = true;
  document.querySelectorAll('#filters input[type="checkbox"]').forEach(function (c) { c.checked = false; });
  var slider = document.getElementById('max-price');
  slider.value = slider.max;
  applyFilters();
});

// Filters open as a panel on phones
document.getElementById('open-filters').addEventListener('click', function () {
  document.getElementById('filters').classList.remove('hidden');
});
document.getElementById('close-filters').addEventListener('click', function () {
  document.getElementById('filters').classList.add('hidden');
});

// ---------- Sorting ----------
function score(f) {
  if (sortBy === 'price') return +f.dataset.price;
  if (sortBy === 'minutes') return +f.dataset.minutes;
  // Best value: price and journey time count equally, compared with the best of each
  return +f.dataset.price / lowest.price + +f.dataset.minutes / lowest.minutes;
}

function sortFlights() {
  allFlights.slice().sort(function (a, b) {
    return score(a) - score(b) || a.dataset.order - b.dataset.order;
  }).forEach(function (f) { results.appendChild(f); });
}

document.querySelectorAll('.sort').forEach(function (button) {
  button.addEventListener('click', function () {
    sortBy = button.dataset.sort;
    document.querySelectorAll('.sort').forEach(function (b) { b.setAttribute('aria-pressed', b === button); });
    sortFlights();
  });
});

// ---------- Flight details ----------
results.addEventListener('click', function (e) {
  var toggle = e.target.closest('.details-toggle');
  if (!toggle) return;
  var details = toggle.closest('.flight').querySelector('.details');
  var open = details.classList.toggle('hidden') === false;
  toggle.setAttribute('aria-expanded', open);
  toggle.textContent = open ? 'Hide details' : 'Flight details';
});

// ---------- How long the prices are held ----------
function startTimer(expiresAt) {
  var timer = document.getElementById('price-timer');
  var ends = new Date(expiresAt).getTime();
  timer.classList.remove('hidden');

  function tick() {
    var left = Math.round((ends - Date.now()) / 1000);
    if (left <= 0) {
      clearInterval(interval);
      timer.classList.add('hidden');
      document.getElementById('expired').classList.remove('hidden');
      results.querySelectorAll('a.btn').forEach(function (a) {
        a.classList.add('pointer-events-none', 'opacity-50');
        a.setAttribute('aria-disabled', 'true');
      });
      return;
    }
    var m = Math.floor(left / 60), s = left % 60;
    timer.textContent = 'Prices held for ' + m + ':' + (s < 10 ? '0' : '') + s;
  }
  var interval = setInterval(tick, 1000);
  tick();
}

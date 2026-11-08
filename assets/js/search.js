// Search box: tabs, trip type, multi-city flights, airport suggestions and the travellers picker.
// The forms still work without this file.

// ---------- Tabs ----------
document.querySelectorAll('[data-tab]').forEach(function (tab) {
  tab.addEventListener('click', function (e) {
    e.preventDefault();
    document.querySelectorAll('[data-tab]').forEach(function (t) {
      var selected = t === tab;
      t.setAttribute('aria-selected', selected);
      document.getElementById('panel-' + t.dataset.tab).hidden = !selected;
    });
  });
});

// ---------- Trip type: return / one way / multi-city ----------
var legs = document.querySelectorAll('.flight-leg');
var returnBox = document.getElementById('return-box');
var returnDate = document.getElementById('return');
var addFlightBox = document.getElementById('add-flight-box');

function showLeg(leg, show) {
  leg.hidden = !show;
  leg.querySelectorAll('input').forEach(function (input) { input.disabled = !show; });
}

function countVisibleLegs() {
  return Array.from(legs).filter(function (leg) { return !leg.hidden; }).length;
}

function setTrip(trip) {
  returnBox.hidden = trip !== 'return';
  returnDate.disabled = trip !== 'return';
  addFlightBox.hidden = trip !== 'multi';

  if (trip === 'multi') {
    if (countVisibleLegs() < 2) {
      showLeg(legs[1], true);
      startWherePreviousLanded(1);
    }
  } else {
    for (var i = 1; i < legs.length; i++) showLeg(legs[i], false);
  }
}

// A new flight starts where the previous one landed
function startWherePreviousLanded(i) {
  var prevTo = legs[i - 1].querySelectorAll('.airport-input')[1];
  var from = legs[i].querySelectorAll('.airport-input')[0];
  if (from.value === '') {
    from.value = prevTo.value;
    from.nextElementSibling.value = prevTo.nextElementSibling.value;
  }
}

document.querySelectorAll('input[name="trip"]').forEach(function (radio) {
  radio.addEventListener('change', function () { setTrip(radio.value); });
});

document.getElementById('add-flight').addEventListener('click', function () {
  var i = countVisibleLegs();
  if (i < legs.length) {
    showLeg(legs[i], true);
    startWherePreviousLanded(i);
  }
  if (countVisibleLegs() === legs.length) addFlightBox.hidden = true;
});

// Return date can't be before the departure date
document.getElementById('depart-0').addEventListener('change', function () {
  returnDate.min = this.value;
  if (returnDate.value < this.value) returnDate.value = this.value;
});

// ---------- Airport suggestions ----------
// The visible box shows "Lagos (LOS)"; the hidden input next to it holds "LOS".
document.querySelectorAll('.airport-input').forEach(function (input) {
  var code = input.nextElementSibling;
  var list = code.nextElementSibling;
  var results = [];
  var highlighted = 0;
  var timer;

  function pick(airport) {
    input.value = airport.city + ' (' + airport.code + ')';
    code.value = airport.code;
    input.setCustomValidity('');
    list.hidden = true;
  }

  function highlight(i) {
    highlighted = i;
    list.querySelectorAll('li').forEach(function (li, n) {
      li.classList.toggle('bg-paper-100', n === i);
    });
  }

  function show(airports) {
    results = airports;
    list.innerHTML = '';
    if (airports.length === 0) {
      list.innerHTML = '<li class="px-3 py-2.5 text-sm text-ink-600">No airports found. Try the city name.</li>';
    }
    airports.forEach(function (a, i) {
      var li = document.createElement('li');
      li.className = 'flex cursor-pointer items-center gap-3 px-3 py-2.5 hover:bg-paper-100';
      li.innerHTML =
        '<span class="w-11 shrink-0 rounded bg-ink-900 py-1 text-center text-xs font-bold text-paper-50"></span>' +
        '<span class="min-w-0"><span class="block text-sm font-semibold"></span><span class="block truncate text-xs text-ink-600"></span></span>';
      // textContent, so airport names can never inject HTML
      li.children[0].textContent = a.code;
      li.children[1].children[0].textContent = a.city + (a.country ? ', ' + a.country : '');
      li.children[1].children[1].textContent = a.name;
      li.addEventListener('mousedown', function (e) { e.preventDefault(); pick(a); });
      list.appendChild(li);
    });
    list.hidden = false;
    highlight(0);
  }

  input.addEventListener('input', function () {
    code.value = '';
    input.setCustomValidity('');
    clearTimeout(timer);
    var text = input.value.trim();
    if (text.length < 2) { list.hidden = true; return; }
    timer = setTimeout(function () {
      fetch('api/airport-suggest.php?q=' + encodeURIComponent(text))
        .then(function (response) { return response.json(); })
        .then(show)
        .catch(function () { list.hidden = true; });
    }, 200);
  });

  input.addEventListener('keydown', function (e) {
    if (list.hidden) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); highlight(Math.min(highlighted + 1, results.length - 1)); }
    if (e.key === 'ArrowUp') { e.preventDefault(); highlight(Math.max(highlighted - 1, 0)); }
    if (e.key === 'Enter' && results[highlighted]) { e.preventDefault(); pick(results[highlighted]); }
    if (e.key === 'Escape') list.hidden = true;
  });

  input.addEventListener('blur', function () {
    list.hidden = true;
    // People who know the code can just type it, e.g. "los"
    if (code.value === '' && /^[a-z]{3}$/i.test(input.value.trim())) {
      code.value = input.value.trim().toUpperCase();
    }
  });
});

// ---------- Travellers picker ----------
var adults = document.getElementById('adults');
var children = document.getElementById('children');
var infants = document.getElementById('infants');
var cabin = document.getElementById('cabin');

function updateTravellers() {
  adults.value = Math.min(9, Math.max(1, +adults.value || 1));
  children.value = Math.min(8, Math.max(0, +children.value || 0));
  infants.value = Math.min(+adults.value, Math.max(0, +infants.value || 0));   // one infant per adult
  var total = +adults.value + +children.value + +infants.value;
  document.getElementById('travellers-text').textContent =
    total + (total === 1 ? ' traveller, ' : ' travellers, ') + cabin.options[cabin.selectedIndex].text;
}

document.querySelectorAll('.step').forEach(function (button) {
  button.addEventListener('click', function () {
    var field = button.parentNode.querySelector('input');
    field.value = +field.value + +button.dataset.add;
    updateTravellers();
  });
});
[adults, children, infants, cabin].forEach(function (field) {
  field.addEventListener('change', updateTravellers);
});

// Close the picker when tapping anywhere else
document.addEventListener('click', function (e) {
  var picker = document.getElementById('travellers');
  if (!picker.contains(e.target)) picker.open = false;
});

// ---------- Check before searching ----------
document.getElementById('panel-flights').addEventListener('submit', function (e) {
  var ok = true;
  legs.forEach(function (leg) {
    if (leg.hidden) return;
    var boxes = leg.querySelectorAll('.airport-input');
    var from = boxes[0].nextElementSibling.value;
    var to = boxes[1].nextElementSibling.value;
    if (!from) { boxes[0].setCustomValidity('Choose an airport from the list.'); ok = false; }
    if (!to) { boxes[1].setCustomValidity('Choose an airport from the list.'); ok = false; }
    else if (to === from) { boxes[1].setCustomValidity('Choose a different destination.'); ok = false; }
  });
  if (!ok) {
    e.preventDefault();
    this.reportValidity();
  }
});

// ---------- Swap "From" and "To" ----------
document.querySelectorAll('.swap').forEach(function (button) {
  button.addEventListener('click', function () {
    var boxes = button.closest('.flight-leg').querySelectorAll('.airport-input');
    var text = boxes[0].value, code = boxes[0].nextElementSibling.value;
    boxes[0].value = boxes[1].value;
    boxes[0].nextElementSibling.value = boxes[1].nextElementSibling.value;
    boxes[1].value = text;
    boxes[1].nextElementSibling.value = code;
  });
});

// ---------- Quick picks fill in the first flight ----------
document.querySelectorAll('.quick-pick').forEach(function (button) {
  button.addEventListener('click', function () {
    var boxes = legs[0].querySelectorAll('.airport-input');
    boxes[0].value = button.dataset.fromCity + ' (' + button.dataset.from + ')';
    boxes[0].nextElementSibling.value = button.dataset.from;
    boxes[1].value = button.dataset.toCity + ' (' + button.dataset.to + ')';
    boxes[1].nextElementSibling.value = button.dataset.to;
    boxes[0].setCustomValidity('');
    boxes[1].setCustomValidity('');
    document.getElementById('depart-0').focus();
  });
});

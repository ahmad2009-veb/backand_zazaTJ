const addressModalEl = document.getElementById('create-address');
const addressModal = new bootstrap.Modal(addressModalEl);

const suggest = document.querySelector('#suggest');

const center = [38.5839, 68.7826];
const markerOptions = {
  preset: 'islands#redCircleIcon'
};
let myMap;

function initMap () {
  geolocation = ymaps.geolocation;
  myMap = new ymaps.Map('map', {
    center: center,
    zoom: 14,
    controls: ['smallMapDefaultSet'],
  });

  myMap.controls.remove('searchControl');

  geolocation.get({
    provider: 'yandex',
    mapStateAutoApply: false
  }).then(function (result) {
    // Красным цветом пометим положение, вычисленное через ip.
    result.geoObjects.options.set('preset', 'islands#redCircleIcon');
    result.geoObjects.get(0).properties.set({
      balloonContentBody: 'Мое местоположение'
    });
    myMap.geoObjects.add(result.geoObjects);
    myMap.setCenter(result.geoObjects.get(0).geometry.getCoordinates(), 15);
  });

  geolocation.get({
    provider: 'browser',
    mapStateAutoApply: false
  }).then(function (result) {
    // Синим цветом пометим положение, полученное через браузер.
    // Если браузер не поддерживает эту функциональность, метка не будет добавлена на карту.
    result.geoObjects.options.set('preset', 'islands#blueCircleIcon');
    myMap.geoObjects.add(result.geoObjects);
    myMap.setCenter(result.geoObjects.get(0).geometry.getCoordinates(), 15);
  });

  //myMap.geoObjects.add(new ymaps.Placemark(center, null, markerOptions));

  myMap.events.add('click', (e) => {
    const coords = e.get('coords');

    addPlacemark(coords);

    Livewire.emit('setLatLng', coords);

    ymaps.geocode(coords, {
      kind: 'house',
      results: 1
    }).then(res => {
      const name = res.geoObjects.get(0).properties.get('name');
      suggest.value = name;
      Livewire.emit('setAddress', name);
    });
  });

  const suggestView = new ymaps.SuggestView('suggest');
  suggestView.events.add('select', (e) => {
    const name = e.get('item').value;
    ymaps.geocode(name, {
      results: 1
    }).then(res => {
      const firstGeoObject = res.geoObjects.get(0);
      const coords = firstGeoObject.geometry.getCoordinates();
      const bounds = firstGeoObject.properties.get('boundedBy');

      addPlacemark(coords);

      myMap.setBounds(bounds, {
        checkZoomRange: true
      });

      Livewire.emit('setLatLng', coords);
      Livewire.emit('setAddress', name);
    });
  });
}

function addPlacemark (coords) {
  myMap.geoObjects.removeAll();
  myMap.geoObjects.add(new ymaps.Placemark(coords, null, markerOptions));
}

document.addEventListener('livewire:load', () => ymaps.ready(initMap));

addressModalEl.addEventListener('show.bs.modal', () => {
  suggest.value = '';

  Livewire.emit('openAddressModal');
});

Livewire.on('js:openAddressModal', () => addressModal.show());

Livewire.on('js:closeAddressModal', () => addressModal.hide());

Livewire.on('js:updateAddress', (address) => {
  const coords = [address.latitude, address.longitude];
  addPlacemark(coords);
  myMap.setCenter(coords);

  suggest.value = address.road;
});

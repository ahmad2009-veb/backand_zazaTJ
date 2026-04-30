<template>
  <div id="new-customer-address-modal" class="modal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Укажите адрес доставки</h5>
          <button type="button" class="btn-close" data-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="form-group d-flex align-items-center">
            <input v-model="is_custom_address" class="mr-2" type="checkbox" id="is-custom-address-toggle">
            <label class="mb-0" for="is-custom-address-toggle">Другой адрес</label>
          </div>
          <div class="row mb-3">
            <div class="col-12 col-md-8">

              <div v-if="is_custom_address" class="form-group">
                <input v-model="custom_address" type="text" id="custom-address" class="form-control" placeholder="Укажите адрес (Custom)">
              </div>
              <div v-if="!is_custom_address" class="form-group">
                <input type="text" id="suggest" class="form-control" placeholder="Укажите адрес">
                <div v-if="errors?.road" class="small text-danger">{{ errors?.road && errors.road[0] }}</div>
              </div>
            </div>
            <div v-if="!is_custom_address" class="col-6 col-md-2">
              <input v-model="house" type="text" class="form-control" placeholder="кв">
            </div>
            <div class="col-6 col-md-2">
              <button @click="store" type="button" class="btn btn-success" :disabled="status === 'pending'">Добавить</button>
            </div>
          </div>

          <div id="map" style="width: 100%; height: 400px;"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'NewAddressModal',
  props: {
    customer: Number,
  },

  data() {
    return {
      is_custom_address: false,
      customer_id: this.customer || '',
      status: 'idle',
      custom_address: '',
      address_type: 'home',
      latitude: '',
      longitude: '',
      road: '',
      house: '',
      errors: [],
    };
  }, 

  created () {
    if (!this.customer) {
      this.$emitter.on('customerSelected', id => this.customer_id = id);
    }
  },

  mounted () {
    $('#new-customer-address-modal')
      .on('show.bs.modal', () => this.initMap())
      .on('hidden.bs.modal', () => {
        this.latitude = '';
        this.longitude = '';
        this.road = '';
        this.house = '';

        this.errors = [];

        $('#suggest').val('');
        document.getElementById('map').innerHTML = ''
      });
  },

  methods: {
    initMap () {
      const suggest = $('#suggest').get(0);

      const center = [38.5839, 68.7826];
      const geolocation = ymaps.geolocation;

      const myMap = new ymaps.Map('map', {
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

      myMap.events.add('click', (e) => {
        const coords = e.get('coords');

        this.addPlacemark(myMap, coords);
        this.setLatLng(coords);

        ymaps.geocode(coords, {
          kind: 'house',
          results: 1
        }).then(res => {
          const name = res.geoObjects.get(0).properties.get('name');
          suggest.value = name;
          this.setAddress(name);
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

          this.addPlacemark(myMap, coords);

          myMap.setBounds(bounds, {
            checkZoomRange: true
          });

          this.setLatLng(coords);
          this.setAddress(name);
        });
      });
    },

    addPlacemark (myMap, coords) {
      myMap.geoObjects.removeAll();
      myMap.geoObjects.add(new ymaps.Placemark(coords, null, {
        preset: 'islands#redCircleIcon'
      }));
    },

    setLatLng (coords) {
      const [lat, lng] = coords;
      this.latitude = lat;
      this.longitude = lng;
    },

    setAddress (name) {
      this.road = name;
    },

    store () {
      this.status = 'pending'
      $.ajax({
        url: `/admin/pos/customers/${this.customer_id}/addresses`,
        method: 'POST',
        dataType: 'json',
        data: {
          address_type: this.address_type,
          latitude: this.latitude,
          longitude: this.longitude,
          road: this.is_custom_address ? this.custom_address : this.road,
          house: this.house,
        },
      })
        .done(data => {
          this.$emitter.emit('customerAddressCreated', data);
          this.status = 'fulfilled'

          $('#new-customer-address-modal').modal('hide');

          toastr.success('Адрес добавлен', {
            CloseButton: true,
            ProgressBar: true
          });
        })
        .fail(xhr => {
          this.status = 'rejected'
          this.errors = JSON.parse(xhr.responseText)?.errors
        });
    },
  },
};
</script>
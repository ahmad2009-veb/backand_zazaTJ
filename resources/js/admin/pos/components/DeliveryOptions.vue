<template>
  <div class="pos--delivery-options">
    <div class="d-flex justify-content-between">
      <h5 class="card-title">
        <span>Информация о доставке</span>
      </h5>
    </div>
    <div class="d-flex flex-row">
      <select
        id="customer-address"
        data-placeholder="Выберите адрес"
        class="form-control"
      >
        <option value=""></option>
        <option
          v-for="address in addresses"
          :key="address.id"
          :value="address.id"
        >
          {{ address.road }}
        </option>
      </select>

      <button
        type="button"
        class="btn btn--primary rounded font-regular"
        @click="openNewCustomerAddressModal"
        :disabled="!customer_id"
      >
        Добавить адрес
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'DeliveryOptions',

  data () {
    return {
      addresses: [],
      customer_id: '',
      address_id: '',
    };
  },

  created () {
    this.$emitter.on('customerSelected', id => {
      this.customer_id = id;
      this.loadCustomerAddresses(id);
    });

    this.$emitter.on('customerCreated', () => this.addresses = []);
    this.$emitter.on('customerAddressCreated', (data) => {
      this.address_id = data.id;
      this.addresses.push(data);

      setTimeout(() => {
        $('#customer-address').val(data.id);
        $('#customer-address').trigger('change');
      }, 200);
    });
  },

  mounted () {
    $('#customer-address')
      .select2({
        allowClear: true
      })
      .on('change', e => {
        this.address_id = e.target.value;
        this.$emitter.emit('customerAddressSelected', this.address_id);
      });
  },

  methods: {
    openNewCustomerAddressModal () {
      if (this.customer_id) {
        $('#new-customer-address-modal').modal('show');
      }
    },

    loadCustomerAddresses (id) {
      if (id) {
        $.getJSON(`/admin/pos/customers/${id}/addresses`, data => this.addresses = data);
      } else {
        this.addresses = [];
      }
    },
  }
};
</script>

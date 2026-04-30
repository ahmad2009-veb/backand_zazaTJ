<template>
  <div>
    <div class="w-100">
      <h4 class="card-title">
        Информация о клиент
      </h4>
      <div class="d-flex flex-row p-2 add--customer-btn">
        <select
          id="customer"
          data-placeholder="Выберите клиента"
          class="form-control"
        >
          <option v-if="customer" :value="customer.id" selected="selected">{{ customer.name_with_phone }}</option>
        </select>
        <button
          @click="openNewCustomerModal"
          class="btn btn--primary rounded font-regular"
          type="button"
        >
          Добавить
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Customer',

  data () {
    return {
      customer: '',
    };
  },

  created () {
    this.$emitter.on('customerCreated', data => this.customer = data);
  },

  mounted () {
    $('#customer')
      .select2({
        allowClear: true,
        ajax: {
          url: '/admin/pos/customers',
          data: params => ({
            q: params.term,
            page: params.page
          }),
          processResults: data => ({
            results: data
          }),
        }
      })
      .on('change', e => this.$emitter.emit('customerSelected', e.target.value));
  },

  methods: {
    openNewCustomerModal () {
      $('#new-customer-dialog').modal('show');
    },
  }
};
</script>

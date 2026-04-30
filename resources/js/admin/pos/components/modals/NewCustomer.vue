<template>
  <div id="new-customer-dialog" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header bg-light py-3">
          <h4 class="modal-title">Новый клиент</h4>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="row pl-2">
            <div class="col-12 col-lg-6">
              <div class="form-group">
                <label class="input-label">
                  Имя
                  <span class="input-label-secondary text-danger">*</span>
                </label>
                <input
                  v-model="f_name"
                  type="text"
                  class="form-control"
                  placeholder="Имя"
                >
                <div v-if="errors.f_name" class="small text-danger">{{ errors.f_name[0] }}</div>
              </div>
            </div>
            <div class="col-12 col-lg-6">
              <div class="form-group">
                <label class="input-label">
                  Фамилия
                </label>
                <input
                  v-model="l_name"
                  type="text"
                  class="form-control"
                  placeholder="Фамилия"
                >
                <div v-if="errors.l_name" class="small text-danger">{{ errors.l_name[0] }}</div>
              </div>
            </div>
          </div>
          <div class="row pl-2">
            <div class="col-12 col-lg-6">
              <div class="form-group">
                <label class="input-label">Электронная почта</label>
                <input
                  v-model="email"
                  type="email"
                  class="form-control"
                  placeholder="Электронная почта"
                >
                <div v-if="errors.email" class="small text-danger">{{ errors.email[0] }}</div>
              </div>
            </div>
            <div class="col-12 col-lg-6">
              <div class="form-group">
                <label class="input-label">
                  Телефон (С кодом страны)
                  <span class="input-label-secondary text-danger">*</span>
                </label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text">+992</span>
                  </div>
                  <input
                    v-model="phone"
                    type="text"
                    class="form-control"
                    placeholder="Телефон"
                  >
                </div>
                <div v-if="errors.phone" class="small text-danger">{{ errors.phone[0] }}</div>
              </div>
            </div>
          </div>

          <div class="btn--container justify-content-end">
            <button type="reset" class="btn btn--reset" data-dismiss="modal">Отмена</button>
            <button
              @click="store"
              type="buttom"
              id="submit_new_customer"
              class="btn btn--primary"
            >
              Создать
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'NewCustomerModal',

  data () {
    return {
      f_name: '',
      l_name: '',
      email: '',
      phone: '',
      errors: [],
    };
  },

  mounted () {
    $('#new-customer-dialog').on('hidden.bs.modal', () => {
      this.f_name = '';
      this.l_name = '';
      this.email = '';
      this.phone = '';

      this.errors = [];
    });
  },

  methods: {
    store () {
      $.ajax({
        url: '/admin/pos/customers',
        method: 'POST',
        dataType: 'json',
        data: {
          f_name: this.f_name,
          l_name: this.l_name,
          email: this.email,
          phone: '+992'+this.phone,
        },
      })
        .done(data => {
          this.$emitter.emit('customerCreated', data);

          $('#new-customer-dialog').modal('hide');

          toastr.success('Клиент добавлен', {
            CloseButton: true,
            ProgressBar: true
          });
        })
        .fail(xhr => this.errors = JSON.parse(xhr.responseText)?.errors);
    },
  }
};
</script>

<template>
  <div class="card">
    <div class="card-header bg-light border-0">
      <h5 class="card-title">
        <span class="card-header-icon"><i class="tio-money-vs"></i></span>
        <span>Раздел выставления счетов</span>
      </h5>
    </div>
    <div class="card-body">
      <div v-if="isCartEmpty" class="alert alert-warning">
        Корзина пуста
      </div>
      <div v-else>
        <customer/>
        <delivery-options/>
        <cart :data="cart"/>
      </div>
    </div>
  </div>
</template>

<script>
import Cart from './Cart.vue';
import Customer from '@/admin/pos/components/Customer.vue';
import DeliveryOptions from '@/admin/pos/components/DeliveryOptions.vue';

export default {
  name: 'Order',

  components: { Cart, Customer, DeliveryOptions },

  data () {
    return {
      cart: [],
      customer_id: '',
      address_id: '',
    };
  },

  created () {
    this.loadCart();

    this.$emitter.on('updateCart', data => this.cart = data);
    this.$emitter.on('customerSelected', data => this.customer_id = data);
    this.$emitter.on('customerCreated', data => this.customer_id = data);
    this.$emitter.on('customerAddressSelected', data => this.address_id = data);
    this.$emitter.on('customDeliveryCharge', customDeliveryCharge => this.setCustomDeliveryCharge(customDeliveryCharge));
    this.$emitter.on('placeOrder', (data) => this.placeOrder(data));
  },

  computed: {
    isCartEmpty () {
      return this.cart.length == 0;
    },

    canOrder () {
      return this.cart.length && this.customer_id && this.address_id;
    },
  },

  methods: {
    loadCart () {
      $.getJSON('/admin/pos/cart', data => this.cart = data);
    },

    setCustomDeliveryCharge (customDeliveryCharge) {
      $.ajax({
        url: '/admin/pos/set/custom-delivery-charge',
        method: 'POST',
        dataType: 'json',
        data: {
          custom_delivery_charge: customDeliveryCharge || 0,
        },
      }).done(cart => this.cart = cart);
    },
    
    placeOrder (data) {
      toastr.clear();

      if (!this.customer_id) {
        toastr.error('Выберите клиента', {
          CloseButton: true,
          ProgressBar: true
        });
      } else if (!this.address_id) {
        toastr.error('Выберите адрес', {
          CloseButton: true,
          ProgressBar: true
        });
      } else {
        $.ajax({
          url: '/admin/pos/place-order',
          method: 'POST',
          dataType: 'json',
          data: {
            cart: this.cart,
            customer_id: this.customer_id,
            address_id: this.address_id,
            ...data,
          },
        }).done(() => {
          toastr.success('Заказ оформлен', {
            CloseButton: true,
            ProgressBar: true
          });

          this.cart = [];
          this.customer_id = '';
          this.address_id = '';
        }).fail(() => {
          toastr.error('Невозможно оформить заказ', {
            CloseButton: true,
            ProgressBar: true
          });
        });
      }
    }
  },
};
</script>

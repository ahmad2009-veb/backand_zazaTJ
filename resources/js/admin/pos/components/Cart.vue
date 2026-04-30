<template>
  <div class='w-100 p-2' id="cart">
    <ul class="clearfix pos-items">
      <cart-item
        v-for="item in cart.items"
        :key="item.id"
        :data="item"
      />
    </ul>

    <div class="total">Промежуточный итог: <span class="pe-2">{{ cart.total_price }} c</span></div>
    <div class="total">Скидка по купону: <span class="pe-2">{{ cart.coupon_discount_amount }} c</span></div>
    <div class="total" v-if="!customDeliveryCharge">Сумма доставки: <span class="pe-2">{{ deliveryCharge }} c</span></div>
    <div class="total">Сумма доставки: <span class="pe-2"><input v-model="customDeliveryCharge" type="number"></span></div>
    <div class="total">Сумма заказа: <span class="pe-2">{{ cart.total }} c</span></div>
    
    <div>
      <label for="order-notes">Примечание</label>
      <input type="text" v-model="orderNotes" id="order-notes" />
    </div>
    
    <div class="row button--bottom-fixed g-1 bg-white">
      <div class="col-sm-6">
        <button
          type="submit"
          @click="placeOrder()"
          class="btn btn--primary btn-sm btn-block"
        >
          Добавить заказать
        </button>
      </div>
      <div class="col-sm-6">
        <button
          type="button"
          @click="clearCart()"
          class="btn btn--reset btn-sm btn-block"
        >
          Очистить корзину
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import CartItem from './cart/CartItem.vue';

export default {
  name: 'Cart',

  components: {
    CartItem,
  },

  props: ['data'],

  data () {
    return {
      cart: this.data,
      customDeliveryCharge: '',
      orderNotes: '',
    };
  },

  computed: {
    deliveryCharge () {
      return this.cart.delivery.is_free ? 0 : this.cart.delivery.charge;
    }
  },

  watch: {
    data (value) {
      this.cart = value;
    },

    customDeliveryCharge (value) {
      this.$emitter.emit('customDeliveryCharge', value);
    },
  },

  methods: {
    placeOrder () {
      this.$emitter.emit('placeOrder', {
        order_notes: this.orderNotes,
      });
    },

    async clearCart () {
      if (confirm('Вы действительно хотите очистить корзину?')) {
        await $.post('/admin/pos/cart/clear', () => {
          this.$emitter.emit('updateCart', []);
        });
      }
    },
  },
};
</script>

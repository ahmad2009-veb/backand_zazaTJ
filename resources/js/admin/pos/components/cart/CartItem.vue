<template>
  <li>
    <div class="fs-6 fw-bold">
      <a @click="deleteCartItem(item)" href="javascript:;" class="me-2">
        <i class="icon_close_alt2" style="display: inline-block; width: 16px; height: 16px"></i>
      </a>
      <span class="p-quantity fw-normal">{{ item.form.quantity }}x</span> {{ item.product.name }}
      <div class="cart_p_i">
        <div class="fs-6 fw-bold">{{ item.form.price }} c</div>
        <div class="numbers-row">
          <input
            type="text"
            class="form-control"
            name="quantity"
            :value="item.form.quantity"
            readonly
          >
          <div @click="incrementQty(item)" class="inc button_inc">+</div>
          <div @click="decrementQty(item)" class="dec button_inc">-</div>
        </div>
      </div>
    </div>
    <div v-if="hasOptions" class="small">
      Вариации: {{ options }}
    </div>
    <div v-if="hasExtra" class="small">
      Дополнения: {{ extra }}
    </div>
  </li>
</template>

<script>
export default {
  name: 'CartItem',

  props: ['data'],

  data () {
    return {
      item: this.data,
    };
  },

  computed: {
    hasOptions () {
      return this.item.form.options && this.item.form.options.length > 0;
    },

    hasExtra () {
      return this.item.form.extra && this.item.form.extra.filter(item => item.quantity > 0).length > 0;
    },

    options () {
      return this.item.form.options.join(', ');
    },

    extra () {
      return this.item.form.extra
        .filter(item => item.quantity > 0)
        .map(item => `${item.quantity}x ${item.name}`)
        .join(', ');
    },
  },

  methods: {
    incrementQty (item) {
      const data = JSON.parse(JSON.stringify(item));
      data.form.quantity = parseInt(data.form.quantity) + 1;
      this.updateCartItem(item.id, data.form);
    },

    decrementQty (item) {
      const data = JSON.parse(JSON.stringify(item));

      data.form.quantity = parseInt(data.form.quantity) - 1;
      if (data.form.quantity == 0) {
        data.form.quantity = 1;
      }
      this.updateCartItem(item.id, data.form);
    },

    async updateCartItem (id, item) {
      await $.post(`/admin/pos/cart/items/${id}/update`, { item }, data => {
        this.$emitter.emit('updateCart', data);

        this.item = data.items.find(item => item.id == id);
      });
    },

    async deleteCartItem ({ id }) {
      await $.post(`/admin/pos/cart/items/${id}/remove`, data => {
        this.$emitter.emit('updateCart', data);
      });
    },
  },
}
</script>

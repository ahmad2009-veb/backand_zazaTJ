<template>
  <div id="product-details-dialog" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="btn-close" data-dismiss="modal"></button>
        </div>
        <div class="modal-body p-0">
          <div class="content">
            <h5 class="modal-title">{{ product.name }}</h5>
            <div v-if="hasOptions">
              <div
                v-for="(option, key) in product.options"
                :key="key"
              >
                <h5>{{ option.title }}</h5>
                <ul class="clearfix">
                  <li
                    v-for="item in option.options"
                  >
                    <label class="container_radio">
                      {{ item }}
                      <input
                        type="radio"
                        @change="updateOption(key, item)"
                        :value="item"
                        :name="option.name"
                        :checked="isOptionSelected(item)"
                      >
                      <span class="checkmark"></span>
                    </label>
                  </li>
                </ul>
              </div>
            </div>

            <div v-if="hasExtra">
              <h5>Добавить к заказу?</h5>
              <div class="form-group p-adds">
                <div
                  v-for="(item, key) in product.extra"
                  class="row small-gutters"
                >
                  <label class="col container_check">
                    {{ item.name }}
                    <input
                      type="checkbox"
                      @change="toggleExtra(key, $event.target.checked)"
                      :checked="isExtraSelected(key)"
                    >
                    <span class="checkmark"></span>
                  </label>
                  <div class="col" :class="{ 'd-none': !isExtraSelected(key) }">
                    <div class="numbers-row">
                      <input type="text"
                             :value="getExtraQuantity(key)"
                             class="form-control form-control-sm"
                             min="1"
                             readonly
                      />
                      <div @click="incrementExtraQty(key)" class="inc button_inc">+</div>
                      <div @click="decrementExtraQty(key)" class="dec button_inc">-</div>
                    </div>
                  </div>
                  <div class="col text-end">{{ item.price }} c</div>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <div class="m-qtt">
              <div class="numbers-row">
                <input v-model="quantity" type="text" class="form-control" name="quantity" readonly>
                <div @click="incrementQty" class="inc button_inc">+</div>
                <div @click="decrementQty" class="dec button_inc">-</div>
              </div>
              <div class="pr-total"><span>{{ totalPrice }} c.</span></div>
            </div>
            <button @click="addToCart()" type="button" class="btn btn_1">В корзину</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ProductDetailsModal',

  data () {
    return {
      product: {},
      quantity: 1,
      options: [],
      extra: [],
    };
  },

  created () {
    this.$emitter.on('openDetails', (id) => this.loadDetails(id));
  },

  mounted () {
    $('#product-details-dialog').on('hidden.bs.modal', () => {
      this.product = {};
      this.quantity = 1;
      this.options = [];
      this.extra = [];
    });
  },

  computed: {
    hasOptions () {
      return this.product.options && this.product.options.length > 0;
    },

    hasExtra () {
      return this.product.extra && this.product.extra.length > 0;
    },

    totalPrice () {
      let totalPrice = 0;

      if (this.options.length) {
        const selectedVariationValue = this.options.join('-');
        const selectedVariation = this.product.variations.find(variation => variation.type == selectedVariationValue);
        const variationPrice = parseFloat(selectedVariation.price);
        const totalVariationPrice = (
          variationPrice - (this.product.price - this.product.discounted_price)
        ) * this.quantity;

        totalPrice += totalVariationPrice;
      } else {
        totalPrice += this.product.discounted_price * this.quantity;
      }

      this.extra.forEach((item, key) => {
        totalPrice += item.quantity * parseFloat(this.product.extra[key].price);
      });

      return totalPrice;
    },
  },

  methods: {
    loadDetails (id) {
      $.getJSON(`/admin/pos/foods/${id}/details`, data => {
        this.product = data.product;
        this.options = data.options;
        this.extra = data.extra;
      });
    },

    isOptionSelected (value) {
      return this.options.includes(value);
    },

    updateOption (key, value) {
      this.options[key] = value;
    },

    isExtraSelected (key) {
      return this.extra[key].quantity > 0;
    },

    getExtraQuantity (key) {
      return this.extra[key].quantity;
    },

    toggleExtra (key, checked) {
      if (checked) {
        this.extra[key].quantity = 1;
      } else {
        this.extra[key].quantity = 0;
      }
    },

    incrementExtraQty (key) {
      this.extra[key].quantity += 1;
    },

    decrementExtraQty (key) {
      if (this.extra[key].quantity > 1) {
        this.extra[key].quantity -= 1;
      } else {
        this.extra[key].quantity = 1;
      }
    },

    incrementQty () {
      this.quantity++;
    },

    decrementQty () {
      if (this.quantity > 1) {
        this.quantity -= 1;
      } else {
        this.quantity = 1;
      }
    },

    addToCart () {
      $.post('/admin/pos/cart/add', {
        product: this.product,
        quantity: this.quantity,
        options: this.options,
        extra: this.extra,
      }, (cart) => {
        this.$emitter.emit('updateCart', cart);

        $('#product-details-dialog').modal('hide');
      });
    }
  },
};
</script>

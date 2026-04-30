<template>
  <div class="order--item-box item-box col-auto">
    <div @click="openDetails(data)" class="product-card cursor-pointer card">
      <div class="card-header inline_product clickable p-0 initial-50">
        <div class="d-flex align-items-center justify-content-center d-block">
          <img class="w-100 rounded" :src="data.image">
        </div>
      </div>
      <div class="card-body inline_product text-center py-2 px-2 clickable">
        <div class="position-relative product-title1 text-dark font-weight-bold text-capitalize">
          {{ data.name }}
        </div>
        <div class="justify-content-between text-center">
          <div class="product-price text-center">
            <span class="text-accent font-weight-bold text-yellow">
              {{ data.price }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Food',

  props: {
    data: Object,
  },

  methods: {
    openDetails ({ id }) {
      this.$emitter.emit('openDetails', id);

      $('#product-details-dialog').modal('show');
    },
  },
};
</script>

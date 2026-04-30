<template>
  <div class="initial-51">
    <section class="section-content padding-y-sm bg-default mt-1">
      <div class="container-fluid content">
        <div class="d-flex flex-wrap">
          <div class="order--pos-left">
            <search />
          </div>
          <div class="order--pos-right">
            <order />
          </div>
        </div>
      </div>
    </section>
  </div>

  <product-details-modal />
  <new-customer-modal />
  <new-address-modal />
</template>

<script>
import NewAddressModal from './components/modals/NewAddress.vue';
import NewCustomerModal from './components/modals/NewCustomer.vue';
import ProductDetailsModal from './components/modals/ProductDetails.vue';
import Order from './components/Order.vue';
import Search from './components/Search.vue';

export default {
  components: {
    NewAddressModal,
    NewCustomerModal,
    ProductDetailsModal,
    Order,
    Search,
  }
};
</script>

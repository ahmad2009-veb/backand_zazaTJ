<template>
    <div class="mt-auto">
        <button
            type="button"
            class="btn btn--primary rounded font-regular"
            @click="openNewCustomerAddressModal"
            :disabled="!customer_id"
        >
            Изменить адрес
        </button>
    </div>
    <new-address-modal :customer="customer_id"/>
</template>

<script>
import NewAddressModal from "@/admin/pos/components/modals/NewAddress.vue";

export default {
    name: "EditUserAddress",
    components: {
        NewAddressModal,
    },
    created() {
        this.customer_id = parseInt(window.location.pathname.split('/').slice(-1))
        this.$emitter.on('customerAddressCreated', () => {
            window.location.reload()
        });
    },
    data() {
        return {
            customer_id: this.customer_id,
        }
    },
    methods: {
        openNewCustomerAddressModal() {
            if (this.customer_id) {
                $('#new-customer-address-modal').modal('show');
            }
        },
    }
}
</script>

<style scoped>

</style>

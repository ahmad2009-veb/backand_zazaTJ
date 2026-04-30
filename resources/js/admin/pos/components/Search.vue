<template>
  <div class="card padding-y-sm card h-100">
    <div class="card-header bg-light border-0">
      <h5 class="card-title">
        <span class="card-header-icon"><i class="tio-fastfood"></i></span>
        <span>Раздел продуктов</span>
      </h5>
    </div>
    <div class="card-header border-0 pt-4">
      <div class="w-100">
        <div class="row g-3 justify-content-around">
          <div class="col-sm-6">
            <select
              id="zone"
              class="form-control h--45x"
              data-placeholder="Выбрать зону"
            >
              <option value=""></option>
              <option
                v-for="zone in zones"
                :key="zone.id"
                :value="zone.id"
              >
                {{ zone.name }}
              </option>
            </select>
          </div>
          <div class="col-sm-6">
            <select
              id="restaurant"
              class="form-control h--45x"
              data-placeholder="Выбрать ресторан"
              :disabled="!zone"
            >
              <option value=""></option>
              <option
                v-for="restaurant in restaurants"
                :key="restaurant.id"
                :value="restaurant.id"
              >
                {{ restaurant.name }}
              </option>
            </select>
          </div>
          <div class="col-sm-6">
            <select
              id="category"
              class="form-control h--45x"
              data-placeholder="Выбрать категорию"
              :disabled="!restaurant"
            >
              <option value=""></option>
              <option
                v-for="category in categories"
                :key="category.id"
                :value="category.id"
              >
                {{ category.name }}
              </option>
            </select>
          </div>
          <div class="col-sm-6">
            <div class="input-group input-group-merge input-group-flush w-100">
              <div class="input-group-prepend pl-2">
                <div class="input-group-text">
                  <i class="tio-search"></i>
                </div>
              </div>
              <input
                v-model="q"
                type="text"
                class="form-control flex-grow-1 pl-5 border rounded h--45x"
                :disabled="!restaurant"
              >
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="card-body d-flex flex-column justify-content-center">
      <div v-if="hasFoods" class="row g-3 mb-auto">
        <food
          v-for="food in foods.data"
          :key="food.id"
          :data="food"
          class="order--item-box item-box col-auto"
        />
      </div>
      <div v-else class="my-auto">
        <div class="search--no-found">
          <img src="/assets/admin/img/search-icon.png" alt="img">
          <p>Чтобы получить требуемый результат поиска, сначала выберите зону, а затем ресторан для поиска категории продукта или выполните поиск вручную, чтобы найти продукт в этом ресторане</p>
        </div>
      </div>
    </div>
    <div class="card-footer border-0 pt-0">
      <nav v-if="isPaginatable">
        <ul class="pagination">
          <li
            v-for="(link, index) in foods.links"
            :key="index"
            :class="{ 'active': link.active }"
            class="page-item"
          >
            <button
              v-if="link.url"
              @click="loadFoods(link.url)"
              type="button"
              class="page-link"
              v-html="link.label"
            ></button>
            <span v-else class="page-link" v-html="link.label"></span>
          </li>
        </ul>
      </nav>
    </div>
  </div>
</template>

<script>
import Food from './Food.vue';

export default {
  name: 'Search',

  components: {
    Food,
  },

  data () {
    return {
      zones: [],
      restaurants: [],
      categories: [],
      foods: {},
      zone: '',
      restaurant: '',
      category: '',
      q: '',
    };
  },

  async created () {
    await this.loadZones();
  },

  mounted () {
    this.initSelects();

    $('#zone').on('change', e => this.zone = e.target.value);
    $('#restaurant').on('change', e => this.restaurant = e.target.value);
    $('#category').on('change', e => this.category = e.target.value);
  },

  computed: {
    hasFoods () {
      return this.foods?.data?.length;
    },

    isPaginatable () {
      return this.foods?.last_page > 1;
    },
  },

  watch: {
    zone (value) {
      if (value) {
        this.loadRestaurants(value);
      } else {
        this.restaurants = [];
        this.restaurant = '';
      }
    },

    restaurant (value) {
      if (value) {
        this.loadCategories();
        this.loadFoods();
      } else {
        this.categories = [];
        this.foods = {};
        this.category = '';
        this.q = '';
      }
    },

    category (value) {
      this.loadFoods();
    },

    q (value) {
      this.loadFoods();
    }
  },

  methods: {
    initSelects () {
      const targets = [
        '#zone',
        '#restaurant',
        '#category',
        '#customer-address',
      ];

      targets.forEach(target => {
        $(target).select2({
          allowClear: true
        });
      });
    },

    loadZones () {
      $.getJSON('/admin/pos/zones', data => this.zones = data);
    },

    loadRestaurants (zone) {
      $.getJSON(`/admin/pos/zone/${zone}/restaurants`, data => this.restaurants = data);
    },

    loadCategories () {
      $.getJSON('/admin/pos/categories', data => this.categories = data);
    },

    loadFoods (url = null) {
      $.getJSON(url || '/admin/pos/foods', {
        restaurant_id: this.restaurant,
        category_id: this.category,
        q: this.q,
      }, data => this.foods = data);
    },
  }
};
</script>

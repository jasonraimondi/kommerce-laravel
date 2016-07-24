<template>
  <div>
    <!-- the input field -->
    <input type="text"
           class="lk-typeahead-form"
           placeholder="Search For Product"
           autocomplete="off"
           v-model="query"
           @keydown.down="down"
           @keydown.up="up"
           @keydown.enter="hit"
           @keydown.esc="reset"
           @input="update"/>

    <!-- the list -->
    <ul v-show="hasItems" class="lk-typeahead-results">
      <li v-for="item in items" :class="activeClass($index)">
        <a v-link="{ name: 'product.show', params: {id: item.id} }">{{ item.name }}</a>
      </li>
    </ul>
  </div>
</template>

<script>
  import VueTypeahead from 'vue-typeahead';

  export default {

    extends: VueTypeahead,

    data() {
      return {
        query: '',
        src: '/api/products',
        limit: 15,
        minChars: 3,
      }
    },

    methods: {

      goToPage(item) {
        this.$router.go({name: 'product.show', params: {id: item.id}});
      },

      onHit (item) {
        alert(item)
      }

    }
  }
</script>

<style>
  .lk-typeahead-form {
    margin: 0;
  }

  .lk-typeahead-results {
    width: 100%;
    position: fixed;
    background-color: white;
    list-style-type: none;
    margin: 0;
    padding: 0;
    border-bottom: 1px solid #e6e6e6;
  }

  .lk-typeahead-results li {
    margin: 0;
    padding: 0;
    color: inherit;
  }

  .lk-typeahead-results li:hover {
    background-color: #e6e6e6;
  }

</style>

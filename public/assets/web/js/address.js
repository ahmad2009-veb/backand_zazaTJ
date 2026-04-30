/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./resources/js/address.js":
/*!*********************************!*\
  !*** ./resources/js/address.js ***!
  \*********************************/
/***/ (() => {

var addressModalEl = document.getElementById('create-address');
var addressModal = new bootstrap.Modal(addressModalEl);
var suggest = document.querySelector('#suggest');
var center = [38.5839, 68.7826];
var markerOptions = {
  preset: 'islands#redCircleIcon'
};
var myMap;
function initMap() {
  geolocation = ymaps.geolocation;
  myMap = new ymaps.Map('map', {
    center: center,
    zoom: 14,
    controls: ['smallMapDefaultSet']
  });
  myMap.controls.remove('searchControl');
  geolocation.get({
    provider: 'yandex',
    mapStateAutoApply: false
  }).then(function (result) {
    // Красным цветом пометим положение, вычисленное через ip.
    result.geoObjects.options.set('preset', 'islands#redCircleIcon');
    result.geoObjects.get(0).properties.set({
      balloonContentBody: 'Мое местоположение'
    });
    myMap.geoObjects.add(result.geoObjects);
    myMap.setCenter(result.geoObjects.get(0).geometry.getCoordinates(), 15);
  });
  geolocation.get({
    provider: 'browser',
    mapStateAutoApply: false
  }).then(function (result) {
    // Синим цветом пометим положение, полученное через браузер.
    // Если браузер не поддерживает эту функциональность, метка не будет добавлена на карту.
    result.geoObjects.options.set('preset', 'islands#blueCircleIcon');
    myMap.geoObjects.add(result.geoObjects);
    myMap.setCenter(result.geoObjects.get(0).geometry.getCoordinates(), 15);
  });

  //myMap.geoObjects.add(new ymaps.Placemark(center, null, markerOptions));

  myMap.events.add('click', function (e) {
    var coords = e.get('coords');
    addPlacemark(coords);
    Livewire.emit('setLatLng', coords);
    ymaps.geocode(coords, {
      kind: 'house',
      results: 1
    }).then(function (res) {
      var name = res.geoObjects.get(0).properties.get('name');
      suggest.value = name;
      Livewire.emit('setAddress', name);
    });
  });
  var suggestView = new ymaps.SuggestView('suggest');
  suggestView.events.add('select', function (e) {
    var name = e.get('item').value;
    ymaps.geocode(name, {
      results: 1
    }).then(function (res) {
      var firstGeoObject = res.geoObjects.get(0);
      var coords = firstGeoObject.geometry.getCoordinates();
      var bounds = firstGeoObject.properties.get('boundedBy');
      addPlacemark(coords);
      myMap.setBounds(bounds, {
        checkZoomRange: true
      });
      Livewire.emit('setLatLng', coords);
      Livewire.emit('setAddress', name);
    });
  });
}
function addPlacemark(coords) {
  myMap.geoObjects.removeAll();
  myMap.geoObjects.add(new ymaps.Placemark(coords, null, markerOptions));
}
document.addEventListener('livewire:load', function () {
  return ymaps.ready(initMap);
});
addressModalEl.addEventListener('show.bs.modal', function () {
  suggest.value = '';
  Livewire.emit('openAddressModal');
});
Livewire.on('js:openAddressModal', function () {
  return addressModal.show();
});
Livewire.on('js:closeAddressModal', function () {
  return addressModal.hide();
});
Livewire.on('js:updateAddress', function (address) {
  var coords = [address.latitude, address.longitude];
  addPlacemark(coords);
  myMap.setCenter(coords);
  suggest.value = address.road;
});

/***/ }),

/***/ "./resources/css/custom.css":
/*!**********************************!*\
  !*** ./resources/css/custom.css ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var [chunkIds, fn, priority] = deferred[i];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"/assets/web/js/address": 0,
/******/ 			"assets/web/css/custom": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = self["webpackChunk"] = self["webpackChunk"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	__webpack_require__.O(undefined, ["assets/web/css/custom"], () => (__webpack_require__("./resources/js/address.js")))
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["assets/web/css/custom"], () => (__webpack_require__("./resources/css/custom.css")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
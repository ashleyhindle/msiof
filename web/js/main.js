/**
 * Created by ahindle on 08/11/14.
 */
var msiofApp = angular.module('msiofApp', []).config(function($interpolateProvider){
		  $interpolateProvider.startSymbol('{[{').endSymbol('}]}');
});

msiofApp.controller('HomeCtrl', function ($scope, $http) {
		  $scope.servers = {};
		  console.log($scope.testVar);
		  $http({
					 method: 'GET',
					 url: '/servers/cheese',
		  }).then(function(response){
					 var data = response.data;
					 $scope.servers = data;
					 console.log($scope.servers);
		  });

});

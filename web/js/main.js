/**
 * Created by ahindle on 08/11/14.
 */

var msiofApp = angular.module('msiofApp', ['ui.bootstrap', 'angularMoment']).config(function($interpolateProvider){
		  $interpolateProvider.startSymbol('{[{').endSymbol('}]}');
});

msiofApp.controller('HomeCtrl', function ($scope, $http, $interval) {
		  $scope.servers = {};
		  $scope.loaded = false;
		  $scope.sortBy = '+name';
		  $scope.sortOptions = {
					 'Name': '+name',
					 'Issues': '-hasIssues',
					 'Memory Usage': '-mem.percentage.usage',
					 'CPU Usage': '-cpu.percentage.usage',
					 'Disk Usage': '-disk.percentage.usage',
					 'Network Usage': '-net.total.totalkbps',
					 'Load Average': '-system.loadavg',
		  };

		  $scope.setSort = function(sortValue) {
					 $scope.sortBy = sortValue;
		  }

		  $scope.getConnectionCount = function(server) {
					 return Object.keys(server.conns).length;
		  };

		  $scope.updateServers = function() {
					 $http({
								method: 'GET',
								url: '/servers/' + $scope.apiKey,
					 }).then(function(response){
								var data = response.data;
								$scope.servers = data;
								$scope.loaded = true;
					 });
		  };

		  $scope.apiKeyWatcher = $scope.$watch("apiKey", function(){
					 $scope.updateServers();
					 $interval(function() {
								$scope.updateServers();
					 }, 30000);
					 $scope.apiKeyWatcher();
		  });
});

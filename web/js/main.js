var msiofApp = angular.module('msiofApp', ['ui.bootstrap', 'angularMoment']).config(function($interpolateProvider){
		  $interpolateProvider.startSymbol('{[{').endSymbol('}]}');
});
msiofApp.directive('selectOnClick', function () {
		  return {
					 restrict: 'A',
					 link: function (scope, element, attrs) {
								element.on('click', function () {
										  this.select();
								});
					 }
		  };
});

msiofApp.controller('HomeCtrl', function ($scope, $http, $interval) {
		  $scope.servers = {};
		  $scope.alerts = [];
		  $scope.filter = '';
		  $scope.loaded = false;
		  $scope.showInstallInstructions = false;
		  $scope.sortBy = '+name';
		  $scope.expandAll = false;
		  $scope.deletedServerKeys = [];

		  $scope.addAlert = function(msg, type) {
					 type = typeof type !== 'undefined' ? type : 'success';
					 $scope.alerts.push({'msg': msg, 'type': type});
		  };

		  $scope.closeAlert = function(index) {
					 $scope.alerts.splice(index, 1);
		  };

		  $scope.sortOptions = [
					 { 
								'display': 'Name',
								'value': '+name'
					 },
					 { 
								'display': 'Issues',
								'value': '-hasIssues'
					 },
					 { 
								'display': 'Memory Usage',
								'value': '-mem.percentage.usage'
					 },
					 { 
								'display': 'CPU Usage',
								'value': '-cpu.percentage.usage'
					 },
					 { 
								'display': 'Disk Usage',
								'value': '-disk.percentage.usage'
					 },
					 { 
								'display': 'Network Usage',
								'value': '-network.total.totalkbps'
					 },
					 { 
								'display': 'Load Average',
								'value': '-system.loadavg'
					 },
		  ];

		  $scope.deleteServer = function(serverKey) {
					 $scope.deletedServerKeys.push(serverKey);
					 $http({
								method: 'DELETE',
								url: '/server/' + serverKey,
					 }).then(function(response) {
								if(response.success == false) {
										  $scope.addAlert('Failed to remove server', 'danger');
								} else {
										  $scope.addAlert('Successfully removed server', 'success');
										  $scope.updateServers();
								}
					 });
		  }

		  $scope.setSort = function(sortValue) {
					 $scope.sortBy = sortValue;
		  }

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

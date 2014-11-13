var msiofApp = angular.module('msiofApp', ['ui.bootstrap', 'angularMoment']).config(function($interpolateProvider){
		  $interpolateProvider.startSymbol('{[{').endSymbol('}]}');
});

msiofApp.controller('HomeCtrl', function ($scope, $http, $interval) {
		  $scope.servers = {};
		  $scope.filter = '';
		  $scope.loaded = false;
		  $scope.showInstallInstructions = false;
		  $scope.sortBy = '+name';
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

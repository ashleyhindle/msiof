/* Unit size defaults to 1024, and precision defaults to 1 */
/* Thanks go to http://stackoverflow.com/questions/15900485/correct-way-to-convert-size-in-bytes-to-kb-mb-gb-in-javascript */

angular.module('bytesToNiceFilter', []).filter('bytesToNice', function() {
		  return function(bytes, precision, unitsize) {
					 unitsize = typeof unitsize !== 'undefined' ? parseInt(unitsize) : 1024;
					 precision = typeof precision !== 'undefined' ? parseInt(precision) : 1;

					 if(bytes == 0) return '0';

					 var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
					 var i = Math.floor(Math.log(bytes) / Math.log(unitsize));
					 var nice = (bytes / Math.pow(unitsize, i)).toFixed(precision);
					 console.log(typeof nice);
					 nice += ' ' + sizes[i];
					  
					 return nice;
		  };
});

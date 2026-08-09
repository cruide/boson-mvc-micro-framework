
var boson = {
	values: {},
	fn: {callback: null},
	
	isArray: function(mixed_var) {
		return ( mixed_var instanceof Array );
	},

	isInteger: function( value ) {
		return Number(value) === value && value % 1 === 0;
	},

	isFloat: function( value ) {
		return Number(value) === value && value % 1 !== 0;
	},

	isNumeric: function( value ) {
		return boson.isInteger(value) || boson.isFloat(value);
	},

	isFilled: function( value, trimed ) {
		if( !boson.empty(trimed) && trimed == true ) {
			value = value.trim();
		}

		return value != '';
	},

	isUrl: function(url) {
		var RegExp = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/;

		if( RegExp.test(url) ) {
			return true;
		}

		return false;
	},

	isEmail: function(email) {
		var RegExp = /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;

		if( RegExp.test(email) ) {
			return true;
		}

		return false;
	},

	implode: function(glue, pieces) {
		return ((pieces instanceof Array) ? pieces.join(glue) : pieces);
	},

	explode: function( delimiter, string ) {
		var emptyArray = { 0: '' };

		if ( arguments.length != 2
			|| typeof arguments[0] == 'undefined'
			|| typeof arguments[1] == 'undefined' )
		{
			return null;
		}

		if ( delimiter === ''
			|| delimiter === false
			|| delimiter === null )
		{
			return false;
		}

		if ( typeof delimiter == 'function'
			|| typeof delimiter == 'object'
			|| typeof string == 'function'
			|| typeof string == 'object' )
		{
			return emptyArray;
		}

		if ( delimiter === true ) {
			delimiter = '1';
		}

		return string.toString().split ( delimiter.toString() );
	},

	empty: function(mixed_var) {
		if( typeof(mixed_var) == 'undefined' || mixed_var === '' || mixed_var === 0 || mixed_var === '0' || mixed_var === null || mixed_var === false || (boson.isArray(mixed_var) && mixed_var.length === 0) ) {
			return true;
		}

		return false;
	},

	json: function(href, values, callback) {
		if( typeof(values) == 'undefined' || typeof(values) == 'function' ) {
			this.ajax(href, values, callback, 'GET', true, false);
		} else {
			this.ajax(href, values, callback, 'POST', true, false);
		}
	},

	jsonPut: function(href, values, callback, multipart) {
		this.ajax(href, values, callback, 'PUT', true, false, multipart);
	},
	
    jsonDel: function(href, values, callback, multipart) {
        this.ajax(href, values, callback, 'DELETE', true, false, multipart);
    },

	del: function(href, values, callback) {
		this.ajax(href, values, callback, 'DELETE', true);
	},

	put: function(href, values, callback) {
		this.ajax(href, values, callback, 'PUT', true);
	},
	
	pjax: function(href, selector, values, callback) {
		if( typeof(callback) != 'function' )
		var callback = null;

		if( typeof(values) == 'function' ) {
			callback = values;
			values   = null;

		} else if( typeof(values) == 'undefined' ) {
			values = null;
		}

		this.ajax(href, values, function(r) {
			if( $(selector).length > 0 ) {
				$(selector).html(r);
			}

            if( typeof(callback) == 'function' ) {
			    callback(href, selector, values);
            }

		}, 'GET', false, true);
	},

	ajax: function(href, values, callback, request_type, json, pjax) {
		if( typeof(values) == 'function' ) {
			callback = values;
			values   = null;
		}

		if( typeof(json) == 'undefined' || json == '' ) {
			json = false;
		}

		if( boson.empty(request_type) ) {
			request_type = 'POST';
		}

		if( request_type != 'GET' && request_type != 'POST' && request_type != 'PUT' && request_type != 'DELETE') {
			request_type = 'POST';
		}

		var response = (json == true) ? 'json' : 'html';
		var headers  = {
			'X-Request-Name': 'ZeroRequest',
			'X-Request-Type': response,
		};

		if( pjax == true ) {
			headers['X-PJAX'] = 'Pjax';

			if( typeof(values) == 'undefined' ) {
				request_type = 'GET';
			}
		}
		
		jQuery.ajax({
			url: href, type: request_type, dataType: response,
			headers: headers,
			async: true, data: values, success: function(result) {
                if( !boson.empty(result.redirect) ) {
                    window.location.href = result.redirect;
                }

				if( typeof(callback) == 'function' ) {
					callback(result);
				}
			},

			error: function (jqXHR, textStatus, errorThrown) {
				if( typeof(console) != 'undefined' && typeof(console) != '' ) {
					console.log( jqXHR );
					console.log('Request boson.ajax to `' + href + '` returned an error ' + textStatus);
					console.log( errorThrown );
				}
			},

			timeout: function () {
				if( typeof(console) != 'undefined' && typeof(console) != '' ) {
					console.log('Request boson.ajax to `' + href + '` has timed out');
				}
			}
		});
	},

	redirect: function( href ) {
		if( boson.empty(href) ) {
			href = '/';
		}

		window.location.href = href;
	},

	init: function() {
        $('a.pjax').each(function(n, e) {
            var href = $(this).attr('href');
            var item = $(this).data('item');
            
            $(this).on('click', function() {
                if( !boson.empty(href) && !boson.empty(item) && $(item).length > 0 ) {
                    boson.pjax(href, item, function() {
                        if( href != window.location ) {
                            window.history.pushState(null, null, href);
                        }
                    });
                }

                return false;
            });
            
            $(this).removeAttr('href');
            $(this).removeAttr('item');
            $(this).css({
                cursor: 'pointer'
            });
        });
	},
};

Array.prototype.inArray = function( searchedVal ) {
	return this.filter(function(item, index, array) {
		return ( item == searchedVal );
	}).length == true;
};

Number.prototype.num2word = function( words, bold ) {
	var cases  = [2, 0, 1, 1, 1, 2];
	var num    = this;
	var bold   = boson.empty(bold) ? false : true;
	var result = words[ (num%100>4 && num%100<20) ? 2 : cases[(num%10<5)?num%10:5] ];
	
	if( bold ) {
		return '<strong>' + num + '</strong> ' + result;
	}
	
	return num + ' ' + result;
}

String.prototype.ucfirst = function() {
	var text  = this;
	var parts = text.split(' '), len = parts.length, i, words = [];

	for(i = 0; i < len; i++) {
		var part  = parts[i];
		var first = part[0].toUpperCase();
		var rest  = part.substring(1, part.length);
		var word  = first + rest;

		words.push(word);
	}

	return words.join(' ');
};

if( typeof(jQuery) != 'function' ) {
	alert( 'Need jQuery...' );

} else {
	$(function() { boson.init(); });
}

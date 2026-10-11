/* global Chart, ApiStatsData */
( function () {
	'use strict';

	var data = window.ApiStatsData;

	// Series colors: method colors keep the plugin's original hues; status colors read as ok / redirect / client / server error.
	var SERIES = {
		methods: {
			GET: '#2e9d4f',
			POST: '#8a3ea6',
			PUT: '#d98c00',
			PATCH: '#2f8fbf',
			DELETE: '#c62828',
			OPTIONS: '#8c8f94',
		},
		status: {
			'2xx': '#2e9d4f',
			'3xx': '#2f8fbf',
			'4xx': '#d98c00',
			'5xx': '#c62828',
		},
	};

	function sum( arr ) {
		return arr.reduce( function ( a, b ) {
			return a + b;
		}, 0 );
	}

	function datasets( mode ) {
		var source = data[ mode ];
		return Object.keys( SERIES[ mode ] )
			.filter( function ( key ) {
				return source[ key ] && sum( source[ key ] ) > 0;
			} )
			.map( function ( key ) {
				var color = SERIES[ mode ][ key ];
				return {
					label: key,
					data: source[ key ],
					borderColor: color,
					backgroundColor: color + '33',
					borderWidth: 1.5,
					fill: true,
					tension: 0.25,
					pointRadius: 0,
					pointHitRadius: 6,
				};
			} );
	}

	function initChart() {
		var canvas = document.getElementById( 'api-stats-chart' );
		if ( ! canvas || ! data || typeof Chart === 'undefined' ) {
			return;
		}

		var chart = new Chart( canvas, {
			type: 'line',
			data: { labels: data.labels, datasets: datasets( 'methods' ) },
			options: {
				responsive: true,
				maintainAspectRatio: false,
				animation: { duration: 250 },
				interaction: { mode: 'index', intersect: false },
				scales: {
					x: { stacked: true, grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 16 } },
					y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: data.i18n.requests } },
				},
				plugins: {
					legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } },
					tooltip: {
						callbacks: {
							footer: function ( items ) {
								return data.i18n.requests + ': ' + sum( items.map( function ( i ) { return i.parsed.y; } ) );
							},
						},
					},
				},
			},
		} );

		document.querySelectorAll( '[data-chart-mode]' ).forEach( function ( button, _, all ) {
			button.addEventListener( 'click', function () {
				all.forEach( function ( b ) {
					var active = b === button;
					b.classList.toggle( 'button-primary', active );
					b.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
				} );
				chart.data.datasets = datasets( button.getAttribute( 'data-chart-mode' ) );
				chart.update();
			} );
		} );
	}

	function initTable() {
		var table = document.querySelector( '.api-stats-table' );
		if ( ! table ) {
			return;
		}
		var tbody = table.tBodies[ 0 ];
		var headers = table.querySelectorAll( 'th[data-sort]' );

		function cellValue( row, index, type ) {
			var cell = row.cells[ index ];
			if ( type === 'num' ) {
				return parseFloat( cell.getAttribute( 'data-value' ) ) || 0;
			}
			return cell.textContent.trim().toLowerCase();
		}

		headers.forEach( function ( th, index ) {
			th.tabIndex = 0;
			th.classList.add( 'api-stats-sortable' );

			function sort() {
				var type = th.getAttribute( 'data-sort' );
				var current = th.getAttribute( 'aria-sort' );
				// Numbers start descending (biggest first), text ascending.
				var dir = current ? ( current === 'ascending' ? 'descending' : 'ascending' ) : ( type === 'num' ? 'descending' : 'ascending' );
				var factor = dir === 'ascending' ? 1 : -1;

				var rows = Array.prototype.slice.call( tbody.rows );
				rows.sort( function ( a, b ) {
					var va = cellValue( a, index, type );
					var vb = cellValue( b, index, type );
					return va < vb ? -factor : va > vb ? factor : 0;
				} );
				rows.forEach( function ( r ) {
					tbody.appendChild( r );
				} );

				headers.forEach( function ( h ) {
					h.removeAttribute( 'aria-sort' );
				} );
				th.setAttribute( 'aria-sort', dir );
			}

			th.addEventListener( 'click', sort );
			th.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					sort();
				}
			} );
		} );

		var search = document.querySelector( '.api-stats-search' );
		if ( search ) {
			search.addEventListener( 'input', function () {
				var q = search.value.trim().toLowerCase();
				Array.prototype.forEach.call( tbody.rows, function ( row ) {
					row.hidden = q !== '' && row.textContent.toLowerCase().indexOf( q ) === -1;
				} );
			} );
		}
	}

	function initPurge() {
		var form = document.querySelector( '[data-confirm-purge]' );
		if ( form && data ) {
			form.addEventListener( 'submit', function ( e ) {
				if ( ! window.confirm( data.i18n.confirmPurge ) ) {
					e.preventDefault();
				}
			} );
		}
	}

	initChart();
	initTable();
	initPurge();
}() );

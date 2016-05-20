(function($){
	$(document).ready(function(){
		if($('#username').length && typeof gt_info != 'undefined')
		$('#username').autocomplete({
			source: gt_info.ajaxurl+'?action=autocomplete-username',
			minLength: 2
		});

		if( typeof $.fn.datetimepicker != 'undefined' )
		$('.time-date-picker').datetimepicker({
			controlType: 'select',
			dateFormat: 'yy-mm-dd',
			timeFormat: 'HH:mm:ss z'
		});

		if( typeof $.fn.countdown != 'undefined' )
		$('.countdown').each(function(){
			$(this).countdown({
				end_time: $(this).data('end-time')
			});
		});

		//Highlight a team when hovered
		$(".m_segment").on("mouseover mouseout",function () {
			var $this = $(this);
			var winnderId = $this.attr("data-team-id");
			var $teams = $("[data-team-id="+winnderId+"]");
			$teams.toggleClass('highlight').parent().toggleClass('highlight');
		});
		// highlight each round with its similar reversed round
		function highlightRounds(roundClass){
			roundClass.on('mouseover mouseout',function(){
				roundClass.toggleClass('focus')
			});
		}
		highlightRounds($('.r_64'));
		highlightRounds($('.r_32'));
		highlightRounds($('.r_16'));
		highlightRounds($('.r_8'));
		highlightRounds($('.r_4'));
		highlightRounds($('.r_2'));

		$('#tournament-registration-form').submit(function(e){
			var el = $(this);
			e.preventDefault();

			$.get(
				el.attr('action'),
				el.serialize(),
				function(data){
					var result = el.find('.result');

					result.text(data.message);

					result.fadeIn();
					if( data.success ){
						el.get(0).reset();
					}

					setTimeout(function(){
						result.fadeOut();
						result.text('');
						if( !gt_info.can_edit && data.success )
							location.reload();
					}, 3000);
				},
				'json'
			);
		});

		$('.match_unit').click(function(){
			var round_col = $(this).parents('.round_column');

			var flag = false;
			$(this).find('.m_segment').each(function(){
				if( typeof $(this).attr('data-team-id') == 'undefined' )
					flag = true;
			});

			if(flag) return;


			if( !gt_info.can_edit ){

				
				if( round_col.attr('data-round') != gt_info.tournament_info.current_round ) return;
				
				user_included = false;
				$(this).find('.m_segment').each(function(){
					if($(this).attr('data-team-id') == gt_info.current_user) user_included = true;
				});

				if( !user_included ) return;
			}


			$.get( 
				gt_info.ajaxurl, 
				{
					tournament_id: gt_info.tournament_id,
					match_index: $(this).parent().attr('data-match-index'),
					round: round_col.attr('data-round'),
					action: 'get-match-result-modal'
				},
				function(html) {
					$(html)
						.appendTo('body')
						.modal()
						.submit(function( e ){
							e.preventDefault();

							var dt = $('.match-result').last().serialize();

							$.post( 
								gt_info.ajaxurl, 
								dt,
								function(r) {
									var response = $('.response');
									
									response.text(r.message);
									response.fadeIn();

									setTimeout(function(){
										response.fadeOut();
									}, 4000);
								},
								'json'
							);
						});
				}
			);
		});

	});

	$(window).load(function(){

		if( typeof $.fn.modal != 'undefined' )
		$('.page-load-modal').modal();

	});
})(jQuery);
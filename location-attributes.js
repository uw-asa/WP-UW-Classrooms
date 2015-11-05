jQuery(document).ready(function($){

	function insert_attribute_dropdown(index) {
		var checkbox = $(this).find(':checkbox').get(0);
		var term_id = checkbox.value;
		var meta = 'uw-location-attributes['+term_id+']';
		var disabled = checkbox.checked ? '' : 'disabled="disabled"';
		var quantity = location_attributes[term_id] ? location_attributes[term_id]["quantity"] : '';
		var length = location_attributes[term_id] ? location_attributes[term_id]["length"] : '';
		var width = location_attributes[term_id] ? location_attributes[term_id]["width"] : '';

		return '' +
			' <button type="button" class="attribute-dropdown-button" id="'+checkbox.id+'-button" >' +
			'  <span>V</span>' +
			' </button>' +
			' <div class="attribute-dropdown" id="'+checkbox.id+'-meta" style="display: none;">' +
			'  <ul>' +
			'   <li><label for="quantity">Quantity</label>' +
			'    <input name="'+meta+'[quantity]" type="number" step="1" min="0" id="quantity"' +
			'     value="' + quantity + '" class="small-text" '+disabled+'></li>' +
			'   <li><label for="length">Length</label>' +
			'    <input name="'+meta+'[length]" type="number" step="1" min="0" id="length"' +
			'     value="' + length + '" class="small-text" '+disabled+'></li>' +
			'   <li><label for="width">Width</label>' +
			'    <input name="'+meta+'[width]" type="number" step="1" min="0" id="width"' +
			'     value="' + width + '" class="small-text" '+disabled+'></li>' +
			'  </ul>' +
			' </div>';
	}
	
	$( "#taxonomy-location-attributes .children .selectit" ).after(insert_attribute_dropdown);

	$( "#taxonomy-location-attributes .children .selectit" ).click(function() {
		var checkbox = $(this).find(':checkbox').get(0);
		var meta = 'uw-location-attributes['+checkbox.value+']';
		var button = $('#'+checkbox.id+'-button');
		var inputs = $('#'+checkbox.id+'-meta').find('input');

	    button.attr('disabled', !checkbox.checked);
	    inputs.attr('disabled', !checkbox.checked);
	    button.next().toggle(checkbox.checked);
	});

	$('.attribute-dropdown-button').click(function(){
		$(this).next().toggle();
	});

});

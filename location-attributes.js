jQuery(document).ready(function($){

	function insert_attribute_dropdown(index) {
		return '' +
			'<!-- container for toggle and modal --> ' +
			'<div class="dropdown-container dropdown" style="display: inline-block;">' +
			' <!-- button and modal toggle -->   ' +
			' <button class="btn dropdown-toggle" {{disabled}} data-toggle="dropdown" data-placeholder="false">' +
			'  <span data-toggle="tooltip" title="Open dropdown containing settings for this recording">' +
			'   <i class="fa fa-gear fa-2x"></i>' +
			'  </span>' +
			'  <span class="sr-only">Toggle Dropdown</span>' +
			' </button>' +
			' <!-- end toggle and modal --> ' +
			' <!-- recording settings modal -->' +
        	' <div class="attribute-dropdown" role="menu">' +
        	'  <span class="title">Attribute data</span>' +
        	'  <ul>' +
        	'   <li class="divider"></li>' +
        	'  <li>' +
        	'   <div>' +
        	'    <div class="webcast-indicator fa-stack"><i class="fa fa-rss fa-rotate-225"></i><i class="fa fa-rss fa-rotate-45"></i></div>' +
        	'     Live streaming' +
            '     <span class="pull-right"><label><input type="radio" name="webcast_{{@index}}" value="1"> On</label><label><input type="radio" name="webcast_{{@index}}" value="0" checked> Off</label></span>' +
            '    </div>' +
            '    <div class="setting-description">' +
        	'     Turn on to broadcast live (with a short processing delay).' +
        	'    </div>' +
        	'   </li>' +
        	'   <li>' +
        	'   <div>' +
        	'    <div class="partial-indicator"><i class="fa fa-clock-o fa-fw"></div></i>' +
        	'     Recording duration' +
        	'    </div>' +
        	'    <div class="setting-description">' +
        	'     Adjust the start and/or end time of the recording.  Default is entire class session.' +
        	'    </div>' +
        	'    <div class="slider-box">' +
        	'     <input type="text" value=""/><span class="start-time"></span><span class="end-time pull-right"></span>' +
        	'    </div>' +
        	'   </li>' +
        	'  </ul>' +
        	' </div>' +
        	' <!-- end recording settings modal -->' + 
        	'</div>' +
        	'<!-- end container for toggle and modal -->';
	}
	
	$( "#taxonomy-location-attributes .selectit" ).after(insert_attribute_dropdown);
	
});

@php($clone=FALSE)
@if($o->is_rdn)
	<span class="btn btn-sm btn-outline-focus mt-3"><i class="fas fa-fw fa-exchange"></i> @lang('Rename')</span>
@elseif($edit && $o->can_addvalues)
	<span class="p-0 m-0">
		@switch(get_class($o))
			@case('App\Classes\LDAP\Attribute\Binary\JpegPhoto')
				<span @class(['btn','btn-sm','btn-outline-primary','mt-3','addable','d-none'=>(! $new)]) id="{{ $o->name_lc }}"><i class="fas fa-fw fa-plus"></i> @lang('Upload JpegPhoto')</span>

				@break

			@case('App\Classes\LDAP\Attribute\ObjectClass')
				<button type="button" @class(['btn','btn-sm','btn-outline-primary','mt-3','addable','d-none'=>(! $new)]) data-bs-toggle="modal" data-bs-target="#new_objectclass-modal"><i class="fas fa-fw fa-plus"></i> @lang('Add Objectclass')</button>

				<!-- NEW OBJECT CLASS -->
				<div class="modal fade" id="new_objectclass-modal" tabindex="-1" aria-labelledby="new_objectclass-label" aria-hidden="true" data-bs-backdrop="static">
					<div class="modal-dialog modal-lg modal-fullscreen-lg-down">
						<div class="modal-content">
							<div class="modal-header">
								<h1 class="modal-title fs-5" id="new_objectclass-label">New Object Class</h1>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
							</div>

							<div class="modal-body">
								<x-form.select id="newoc" label="Select from..."/>
							</div>

							<div class="modal-footer">
								<button type="button" class="btn btn-sm btn-primary" data-bs-dismiss="modal">Next</button>
								{{--
								<button type="button" class="btn btn-sm btn-primary" data-bs-dismiss="modal"><i class="fas fa-fw fa-spinner fa-spin d-none"></i> Next</button>
								--}}
							</div>
						</div>
					</div>
				</div>

				@section('page-scripts')
					<script type="text/javascript">
						$(document).ready(function() {
							var added_oc = [];	// Object classes being added to this entry
							var rendered = false;

							// Show our ObjectClass modal so that we can add more objectclasses
							$('#new_objectclass-modal').on('shown.bs.modal',function() {
								if (! rendered)
									$.ajax({
										type: 'POST',
										// @todo When this is opened a second time, the data is appended.
										success: function(data) {
											$('select#newoc').select2({
												dropdownParent: $('#new_objectclass-modal'),
												theme: 'bootstrap-5',
												allowClear: true,
												multiple: true,
												data: data,
											});
										},
										error: function(e) {
											if (e.status != 412)
												alert('That didnt work? Please try again....');
										},
										url: '{{ url('entry/objectclass/add') }}/'+dn,
										cache: false
									});

								rendered = true;
							})

							// When the ObjectClass modal is closed, process what was selected
							$('#new_objectclass-modal').on('hide.bs.modal',function() {
								var c = {{ $o->values->count() }};		// @todo do we need this?
								var newadded = $('select#newoc').val();

								// If nothing selected, we dont have anything to do
								if (added_oc.sort().join('|') == newadded.sort().join('|'))
									return;

								var attrs = $('[data-attr-name]').map(function() {
									return $(this).data('attrName');
								});

								// Find out what was selected, and add them
								newadded.forEach(function (item) {
									if (added_oc.indexOf(item) !== -1)
										return;

									// Add attribute to the page
									$.ajax({
										type: 'POST',
										beforeSend: function() {},
										success: function(data) {
											$('#{{ $o->name }}').append(data);
										},
										error: function(e) {
											if (e.status != 412)
												alert('That didnt work? Please try again....');
										},
										url: '{{ url('entry/attr/add',[$o->name_lc]) }}',
										data: {
											noheader: true,
											value: item,
											objectclasses: oc,
											loop: c++, // @todo can we omit loop and c
										},
										cache: false
									});

									$.ajax({
										type: 'POST',
										beforeSend: function() {},
										success: function(data) {
											// Render any must attributes
											if (data.must.length) {
												data.must.forEach(function(item) {
													// Add attribute to the page
													$.ajax({
														type: 'POST',
														beforeSend: function() {},
														success: function(data) {
															$('#newattrs').append(data);
														},
														error: function(e) {
															if (e.status != 412)
																alert('That didnt work? Please try again....');
														},
														url: '{{ url('entry/attr/add') }}/'+item,
														data: {
															value: item,
															objectclasses: oc,
															loop: c++, // @todo can we omit loop and c
														},
														cache: false
													});
												})
											}

											// Add attributes to "Add new Attribute" that are now available
											if (data.may.length) {
												var newattr = $('select#newattr');

												// @todo It might be nice to re-sort these options
												data.may.forEach(function(item) {
													newattr.append(new Option(item,item,false,false));
												});
											}
										},
										error: function(e) {
											if (e.status != 412)
												alert('That didnt work? Please try again....');
										},
										url: '{{ url('api/schema/objectclass/attrs') }}/'+item,
										cache: false
									});

								});

								// Loop through added_oc, and remove anything not in newadded
								added_oc.forEach(function(item) {
									if (newadded.indexOf(item) === -1) {
										$('span#objectclass_'+item).empty();

										// @todo Remove attributes from "Add new Attribute" that are no longer available
										$.ajax({
											type: 'POST',
											beforeSend: function() {},
											success: function(data) {
												var attrs = [];

												// Remove attributes from "Add new Attribute" that are no longer available
												if (data.may.length) {
													data.may.forEach(function(mayitem) {
														var x = $("select#newattr option[value='"+mayitem+"']");

														if (x.length) {
															x.remove();

														// Add this to the must attrs list, because its been rendered
														} else {
															attrs.push(mayitem);
														}
													});
												}

												data.must.concat(attrs).forEach(function(attr) {
													var x = $('#'+attr).find('input');

													x.css('background-color','#f0c0c0').attr('readonly',true).attr('placeholder',x.val()).val('');
												});

												// remove the Add Values box
												// Remove any keyed in values
												// @todo remove any required attributes that are no longer defined as a result of removing this OC
											},
											error: function(e) {
												if (e.status != 412)
													alert('That didnt work? Please try again....');
											},
											url: '{{ url('api/schema/objectclass/attrs') }}/'+item,
											cache: false
										});
									}
								});

								added_oc = newadded;
							});
						});
					</script>
				@append
				@break

			@case('App\Classes\LDAP\Attribute')
			@default
				@php($clone=TRUE)
				<span @class(['btn','btn-sm','btn-outline-primary','mt-3','addable','d-none'=>(! $new)]) id="{{ $o->name }}-addnew"><i class="fas fa-fw fa-plus"></i> @lang('Add Value')</span>

				@section('page-scripts')
					@if($clone && $edit && $o->can_addvalues)
						<script type="text/javascript">
							$(document).ready(function() {
								// Create a new entry when Add Value clicked
								$('#{{ $o->name }}-addnew.addable').click(function (item) {
									var cln = $(this).parent().parent().find('input:last').parent().clone();
									cln.find('input:last').attr('value','').attr('placeholder', '[@lang('NEW')]');
									cln.appendTo('#'+item.currentTarget.id.replace('-addnew',''));
								});
							});
						</script>
					@endif
				@append
		@endswitch
	</span>
@endif
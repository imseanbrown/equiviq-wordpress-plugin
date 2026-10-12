(function (blocks, element, blockEditor, components, apiFetch) {
  'use strict';
  var el = element.createElement;
  var useEffect = element.useEffect;
  var useState = element.useState;
  var InspectorControls = blockEditor.InspectorControls;
  var TextControl = components.TextControl;
  var SelectControl = components.SelectControl;
  var ToggleControl = components.ToggleControl;
  var PanelBody = components.PanelBody;
  var Notice = components.Notice;
  var kinds = [
    ['folder', 'folders', 'Folders'],
    ['category', 'categories', 'Categories'],
    ['tag', 'tags', 'Tags'],
    ['company', 'companies', 'Companies']
  ];
  var classification = [['folders','Folders'],['category','Category'],['tags','Tags'],['articleId','Article ID'],['version','Version'],['updated','Updated'],['visibility','Visibility']];
  var metadata = [['author','Author'],['updated','Updated'],['published','Published'],['created','Created'],['approved','Approved'],['permalink','Permalink']];

  function groupToggle(label, key, group, groupName, setAttributes) {
    return el(ToggleControl, {
      key: key, label: label, checked: group[key] !== false,
      onChange: function (value) { var next = Object.assign({}, group); next[key] = value; var change = {}; change[groupName] = next; setAttributes(change); }
    });
  }

  blocks.registerBlockType('equiviq-wordpress/guides', {
    edit: function (props) {
      var attrs = props.attributes;
      var set = props.setAttributes;
      var facetsState = useState(null);
      var facets = facetsState[0];
      var setFacets = facetsState[1];
      var errorState = useState(false);
      var error = errorState[0];
      var setError = errorState[1];
      useEffect(function () {
        var active = true;
        apiFetch({path:'/equiviq-wordpress/v1/facets'}).then(function (data) {
          if (active) { setFacets(data); }
        }).catch(function () { if (active) { setError(true); } });
        return function () { active = false; };
      }, []);
      var filters = attrs.filters || {};
      var controls = [];
      kinds.forEach(function (kind) {
        var name = kind[0], property = kind[1], label = kind[2];
        var choices = facets && Array.isArray(facets[property]) ? facets[property] : [];
        if (name === 'company' && choices.length <= 1) { return; }
        ['Include','Exclude'].forEach(function (mode) {
          var field = name + mode;
          controls.push(el(SelectControl, {
            key:field, label:mode + ' ' + label, multiple:true,
            help:'Use Ctrl or Cmd to select more than one.',
            value:Array.isArray(filters[field]) ? filters[field] : [],
            options:choices.map(function (choice) { return {label:choice,value:choice}; }),
            onChange:function (values) {
              var next = Object.assign({}, filters);
              next[field] = Array.isArray(values) ? values : [values];
              set({filters:next});
            }
          }));
        });
      });
      var c = attrs.classification || {}, m = attrs.metadata || {};
      return el(element.Fragment, null,
        el(InspectorControls, null,
          el(PanelBody, {title:'Index & appearance', initialOpen:true},
            el(TextControl, {label:'Index heading',value:attrs.heading || '',onChange:function(v){set({heading:v});}}),
            el(SelectControl, {label:'Callout styling',value:attrs.calloutStyle || 'equiviq',options:[{label:'Inherit from Equiviq',value:'equiviq'},{label:'Inherit from website theme',value:'theme'}],onChange:function(v){set({calloutStyle:v});}})
          ),
          el(PanelBody, {title:'Article filters',initialOpen:false},
            error ? el(Notice, {status:'warning',isDismissible:false}, 'Connect to Equiviq to load filter choices.') : null,
            controls.length ? controls : el('p', null, 'Loading available filters…')
          ),
          el(PanelBody, {title:'Categorization block',initialOpen:false},
            el(ToggleControl, {label:'Show categorization block',checked:c.show !== false,onChange:function(v){set({classification:Object.assign({},c,{show:v})});}}),
            classification.map(function (entry) { return groupToggle('Show '+entry[1],entry[0],c,'classification',set); })
          ),
          el(PanelBody, {title:'Metadata block',initialOpen:false},
            el(ToggleControl, {label:'Show metadata block',checked:m.show !== false,onChange:function(v){set({metadata:Object.assign({},m,{show:v})});}}),
            metadata.map(function (entry) { return groupToggle('Show '+entry[1],entry[0],m,'metadata',set); })
          )
        ),
        el('div', {className:props.className,style:{padding:'24px',border:'1px solid #d9dce2',borderRadius:'12px',background:'#faf9fc'}},
          el('strong', {style:{fontSize:'1.15rem'}}, attrs.heading || 'Guides & Resources'),
          el('p', null, 'Equiviq Guides index and search. Published articles appear beneath this page’s URL.'),
          el('p', {style:{marginBottom:0}}, 'Configure filters and display settings in the block sidebar.')
        )
      );
    },
    save: function () { return null; }
  });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.apiFetch);

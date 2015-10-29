# UW Classrooms Plugin

A plugin for CTE's classroom information pages at the University of Washington.
Each building and classroom is a WordPress "page" with special metadata and taxonomy terms.

## Taxonomies

### location-type

'Building', 'Classroom', etc

### document-type

'Instructions' or 'Schematic'

### location-attributes


## Metadata

### uw-location-sys-id
This is the identifier for the location's record in ServiceNow, aka UW Connect.

### uw-location-data
An associative array containing the location's data, as fetched from ServiceNow.

### uw-access-url
A link to accessibility information for the building, i.e. the Facilities Services' Access Guide

### uw-location-assets
Asset data assigned to this location, as fetched from ServiceNow and specially processed

### uw-album-url
A link to the gallery for this room, i.e. specially tagged photos on Flickr 


## Shortcodes

### [location]
Displays location metadata for the current location page

Attributes:
- __field__: the field to fetch from __uw-location-data__

Example:
```
  [location field=u_fac_code]
```

### [assets]
The data in __uw-location-assets__, formatted for display

### [attributes]
Displays the hierarchy of __location-attributes__ terms for this location

### [accessibility]
Display a link to __uw-access-url__

### [photoalbum]
Display a link to __uw-album-url__

### [instructions]
Display a link to the instructions for this page.
The instructions being any attachment of this page with __document-type__ "Instructions"

### [schematic]
Display the schematic for this page.
The schematic being any attachment of this page with __document_type__ "Schematic"

### [buildings]
Display a formatted list of buildings.
e.g. the pages with __location_type__ "Building"

### [classrooms]
Display a formatted list of classrooms.
e.g. the child pages of a building page


## Dependencies
[The UW WordPress theme](https://github.com/uweb/uw-2014)

[PDF Image Generator](https://wordpress.org/plugins/pdf-image-generator/)

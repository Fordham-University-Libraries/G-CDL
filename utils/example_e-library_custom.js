//example of custom JS you can add to your catalog and ETC. this example is for Sirsi's e-Library OPAC
//the CDL avaialblity endpoint is at /api/?action=check_availability&key=xxxx
const deskToChange = 'Digital Reserves';
const newDeskName = 'desk: Digital Reserves / circ rule: Circulates for 3 hours';
const cdlAppUrl = 'https://www.library.univ.edu/digitalreserves';
var processed = false;
var title = jQuery('title').text();
if (title.includes('Item Display')) {
  //get ckey from add/remove button
  var ckey;
  const hayStack = jQuery('.hit_list_keepremove').text();
  const regex = /put_keepremove_button\('(\d*)'/;
  const found = hayStack.match(regex);
  if (found.length > 1) {
    ckey = found[1];
    console.log(ckey);
    //check every items now since circ folks can't change desk/loc in time
    if (true) {
      //it is, then check with CDL app
      jQuery.getJSON("https://www.library.univ.edu/digitalreserves/api/?action=check_availability&key=" + ckey, function (res) {
        console.log(res)
        if (res.status == 200 && res.data.length) {
          //if the item is on CDL, then process the table
          var cdlItemIndex = 0;
          jQuery('.holdingslist[colspan="3"]').each(function (i) {
            const deskName = jQuery(this).text().trim();
            if (deskName.includes(deskToChange)) {
              jQuery(this).html(`<a href='${cdlAppUrl}/item/${ckey}' target="_blank" style="color:#0000ff"><strong>${newDeskName}</strong></a>`);
              if (res.data[cdlItemIndex] && res.data[cdlItemIndex].available) {
                jQuery(this).append('<br><span style="color:green">Available</span>');
              } else {
                if (res.data[cdlItemIndex] && res.data[cdlItemIndex].timeStr) {
                  jQuery(this).append('<br><span style="color:brown">Unavailable, <small>due:' + res.data[cdlItemIndex].timeStr + '</small></span>');
                } else {
                  jQuery(this).append('<br><span style="color:brown">Unavailable</span>');
                }
              }
              processed = true;
              cdlItemIndex++;
            }
          });
          //if has item on CDL app BUT can't find desk on the page, just add a table
          if (!processed) {
            //console.log('cant find desk');
            var items = '';
            res.data.forEach(item => {
              items += `<tr><td><a href='${cdlAppUrl}/item/${ckey}' target="_blank" style="text-decoration: underline">${item.itemId}</a></td><td>${item.available ? `<a href='${cdlAppUrl}/item/${ckey}' target="_blank" style="color:green; text-decoration: underline">Available</a>` : '<span style="color:brown">Checked Out</span>'}</td>`;
            });
            var myDiv = `<div id="cdl-data" style="background: aliceblue; padding: 1em; margin: 1em 0">
              <p><strong>This item is available online as Digital Reserves</strong></p>
              <table>
              <tr><th style='text-align:left'>Copy</th><th style='text-align:left'>Status</th></tr>
              ${items}
              </table>
              </div>`;
            jQuery('#detail_item_information').after(myDiv)
          }
        }
      });
    }
  }
} else if (title.includes('Full Reserve View')) {
  let regex = /X(\d*)$/;
  let found = window.location.href.match(regex);
  if (found && found.length > 1) {
    barcode = found[1];
    jQuery.getJSON("https://www.library.univ.edu/digitalreserves/api/?action=check_availability&key=" + barcode + "&keyType=itemId", function (res) {
      console.log(res);
      if (res.status == 200 && res.data.length) {
        var cdlItemIndex = 0;
        //the "Reserve Desk" column
        jQuery('tr.sm-selected').each(function (j) {
          jQuery(this).children().each(function (i) {
            if (i < 4) {
              if (jQuery(this).text().trim() == deskToChange) {
                jQuery(this).html(`<a href='${cdlAppUrl}/barcode/${barcode}' target="_blank" style="color:#0000ff"><strong>Digital Reserves</strong></a>`);
                if (res.data[cdlItemIndex] && res.data[cdlItemIndex].available) {
                  jQuery(this).append('<br><span style="color:green">Available</span>');
                } else {
                  if (res.data[cdlItemIndex] && res.data[cdlItemIndex].timeStr) {
                    jQuery(this).append('<br><span style="color:brown">Unavailable, <small>due:' + res.data[cdlItemIndex].timeStr + '</small></span>');
                  } else {
                    jQuery(this).append('<br><span style="color:brown">Unavailable</span>');
                  }
                }
                processed = true;
                cdlItemIndex++;
              }
            }
            i++;

          });
        });
          //if has item on CDL app BUT can't find desk on the page, just add a table
          if (!processed) {
          var items = '';
          res.data.forEach(item => {
            items += `<tr><td><a href='${cdlAppUrl}/item/${ckey}' target="_blank" style="text-decoration: underline">${item.itemId}</a></td><td>${item.available ? `<a href='${cdlAppUrl}/item/${ckey}' target="_blank" style="color:green; text-decoration: underline">Available</a>` : '<span style="color:brown">Checked Out</span>'}</td>`;
          });
          var myDiv = `<div id="cdl-data" style="background: aliceblue; padding: 1em; margin: 1em 0">
            <p><strong>This item is available online as Digital Reserves</strong></p>
            <table>
            <tr><th>Copy</th><th>Status</th></tr>
            ${items}
            </table>
            </div>`;
          jQuery('detail_item_information').after(myDiv)
        }

      }
    });
  } else {
    //if dont have barcode, just link to course search in CDL app for now
    let searchSummary = jQuery('.searchsum_hits').text().trim();
    let regex = /for (.*)$/;
    let found = searchSummary.match(regex);
    if (found && found[1]) {
      let courseName = found[1];
      //console.log(courseName);
      jQuery('.rsvholdings').each(function (i) {
        if (jQuery(this).text().trim() == deskToChange) {
          jQuery(this).html(`<a href='${cdlAppUrl}/search/reserves/COURSE_NAME/${courseName}' target="_blank" style="color:#0000ff"><strong>Digital Reserves</strong></a>`);
          processed = true;
          return false; //break
          //jQuery(this).append('<br><span style="color:green">Available</span>');        
        }
      });
    }
  }
}
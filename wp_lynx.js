function next_thumb(id)
{
	jQuery("#imgprev-btn-" + id).removeAttr("disabled");
	jQuery("#imgprev-btn-" + id).removeClass("disabled");
	llynx_cimgs[id]++;
	if(llynx_cimgs[id] == llynx_imgs[id].length - 1)
	{
		jQuery("#imgnext-btn-" + id).attr("disabled","disabled");
		jQuery("#imgnext-btn-" + id).addClass("disabled");
	}
	jQuery("#icount-" + id).text((llynx_cimgs[id] + 1) + " / " + llynx_imgs[id].length);
	jQuery("#prints" + id + "img").val(llynx_imgs[id][llynx_cimgs[id]]);
	jQuery("#thumbnail-head-llynx-" + id + " > p > .thumbnail").attr("src", llynx_imgs[id][llynx_cimgs[id]]);
}
function prev_thumb(id)
{
	jQuery("#imgnext-btn-" + id).removeAttr("disabled");
	jQuery("#imgnext-btn-" + id).removeClass("disabled");
	llynx_cimgs[id]--;
	if(llynx_cimgs[id] == 0)
	{
		jQuery("#imgprev-btn-" + id).attr("disabled","disabled");
		jQuery("#imgprev-btn-" + id).addClass("disabled");
	}
	jQuery("#icount-" + id).text((llynx_cimgs[id] + 1) + " / " + llynx_imgs[id].length);
	jQuery("#prints" + id + "img").val(llynx_imgs[id][llynx_cimgs[id]]);
	jQuery("#thumbnail-head-llynx-" + id + " > p > .thumbnail").attr("src", llynx_imgs[id][llynx_cimgs[id]]);
}
function img_toggle(id)
{
	if(jQuery("#thumbnail-head-llynx-" + id + " > p > input:checked").length > 0)
	{
		jQuery("#thumbnail-head-llynx-" + id + " > p > .thumbnail").fadeOut();
		jQuery("#imgprev-btn-" + id).attr("disabled","disabled");
		jQuery("#imgprev-btn-" + id).addClass("disabled");
		jQuery("#imgnext-btn-" + id).attr("disabled","disabled");
		jQuery("#imgnext-btn-" + id).addClass("disabled");
	}
	else
	{
		jQuery("#thumbnail-head-llynx-" + id + " > p > .thumbnail").fadeIn();
		if(llynx_cimgs[id] == 0)
		{
			jQuery("#imgprev-btn-" + id).attr("disabled","disabled");
			jQuery("#imgprev-btn-" + id).addClass("disabled");
		}
		else
		{
			jQuery("#imgprev-btn-" + id).removeAttr("disabled");
			jQuery("#imgprev-btn-" + id).removeClass("disabled");
		}
		if(llynx_cimgs[id] == llynx_imgs[id].length - 1)
		{
			jQuery("#imgnext-btn-" + id).attr("disabled","disabled");
			jQuery("#imgnext-btn-" + id).addClass("disabled");
		}
		else
		{
			jQuery("#imgnext-btn-" + id).removeAttr("disabled");
			jQuery("#imgnext-btn-" + id).removeClass("disabled");
		}
	}
}
function next_content(id)
{
	jQuery("#contprev-btn-" + id).removeAttr("disabled");
	jQuery("#contprev-btn-" + id).removeClass("disabled");
	llynx_ccont[id]++;
	if(llynx_ccont[id] == llynx_cont[id].length - 1)
	{
		jQuery("#contnext-btn-" + id).attr("disabled","disabled");
		jQuery("#contnext-btn-" + id).addClass("disabled");
	}
	jQuery("#ccount-" + id).text((llynx_ccont[id] + 1) + " / " + llynx_cont[id].length);
	jQuery("#prints" + id + "content").text(llynx_cont[id][llynx_ccont[id]]);
}
function prev_content(id)
{
	jQuery("#contnext-btn-" + id).removeAttr("disabled");
	jQuery("#contnext-btn-" + id).removeClass("disabled");
	llynx_ccont[id]--;
	if(llynx_ccont[id] == 0)
	{
		jQuery("#contprev-btn-" + id).attr("disabled","disabled");
		jQuery("#contprev-btn-" + id).addClass("disabled");
	}
	jQuery("#ccount-" + id).text((llynx_ccont[id] + 1) + " / " + llynx_cont[id].length);
	jQuery("#prints" + id + "content").text(llynx_cont[id][llynx_ccont[id]]);
}
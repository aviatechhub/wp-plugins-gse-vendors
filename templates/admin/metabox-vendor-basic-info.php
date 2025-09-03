<?php
// Template: Vendor — Basic Information metabox fields (admin only)
// Variables available: $hq, $years, $website, $email, $phone, $whatsapp

if ( ! function_exists( 'gse_vendors_safe_attr' ) ) {
	function gse_vendors_safe_attr( $value ) {
		if ( function_exists( 'esc_attr' ) ) {
			return esc_attr( $value );
		}
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}
?>
<div class="gse-vendor-basic-info">
	<p>
		<label for="gse_hq"><strong>Headquarters</strong></label><br />
		<input type="text" id="gse_hq" name="gse_vendor_meta[headquarters]" class="widefat" value="<?php echo gse_vendors_safe_attr( $hq ); ?>" />
	</p>

	<p>
		<label for="gse_years"><strong>Years in Operation</strong></label><br />
		<input type="number" min="0" id="gse_years" name="gse_vendor_meta[years_in_operation]" value="<?php echo gse_vendors_safe_attr( (string) $years ); ?>" />
	</p>

	<p>
		<label for="gse_website"><strong>Website URL</strong></label><br />
		<input type="url" id="gse_website" name="gse_vendor_meta[website_url]" class="widefat" value="<?php echo gse_vendors_safe_attr( $website ); ?>" />
	</p>

	<fieldset>
		<legend><strong>Contact</strong></legend>
		<p>
			<label for="gse_email">Email</label><br />
			<input type="email" id="gse_email" name="gse_vendor_meta[contact][email]" class="regular-text" value="<?php echo gse_vendors_safe_attr( $email ); ?>" />
		</p>
		<p>
			<label for="gse_phone">Phone</label><br />
			<input type="text" id="gse_phone" name="gse_vendor_meta[contact][phone]" class="regular-text" value="<?php echo gse_vendors_safe_attr( $phone ); ?>" />
		</p>
		<p>
			<label for="gse_whatsapp">WhatsApp</label><br />
			<input type="text" id="gse_whatsapp" name="gse_vendor_meta[contact][whatsapp]" class="regular-text" value="<?php echo gse_vendors_safe_attr( $whatsapp ); ?>" />
		</p>
	</fieldset>

	<p class="description">Use Featured Image as the vendor logo.</p>
</div>



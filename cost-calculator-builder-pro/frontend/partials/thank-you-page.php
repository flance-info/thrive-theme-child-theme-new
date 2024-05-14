<?php
if ( ! isset( $calc_id ) ) {
	$calc_id = null;
}
?>

<div class="thank-you-page" v-show="showThankYouPage" :class="{'loaded': showThankYouPage}">
	<span class="thank-you-page-close" v-if="showCloseBtn" @click.prevent="backToCalculatorAction">
		<span class="ccb-icon-close-x"></span>
	</span>
	<div class="thank-you-page-inner-container">
		<div class="thank-you-page__icon-box">
			<span class="icon-wrapper">
				<span class="icon-content">
					<i class="ccb-icon-Octicons"></i>
				</span>
			</span>
		</div>
		<div class="thank-you-page__title-box">
			<span class="thank-you-page__title-box-title" v-text="thankYouPage.title"></span>
			<span class="thank-you-page__title-box-desc" v-text="thankYouPage.description"></span>
		</div>
		<div class="thank-you-page__order">
			<span>
				<span v-text="thankYouPage.order_title"></span>
				<span v-text="getOrder.orderId"></span>

			</span>

			<span class="display-order"></span>

		</div>

		<div class="thank-you-page__actions">
			<div class="thank-you-page__actions-wrapper">
				<div v-if="showBackToCalculators">
					<button class="<?php echo esc_attr( apply_filters( 'ccb_confirmation_back_button_styles', 'calc-primary', $calc_id ) ); ?>" @click.prevent="backToCalculatorAction">
						<span>
							<i class="ccb-icon-Arrow-Previous"></i>
							<span v-text="thankYouPage.back_button_text"></span>
						</span>
					</button>
				</div>
				<?php do_action( 'ccb_confirmation_add_button', $calc_id ); ?>
				<div v-if="thankYouPage.custom_button">
					<a :href="thankYouPage.custom_button_link" target="_blank" class="<?php echo esc_attr( apply_filters( 'ccb_confirmation_custom_button_styles', 'calc-secondary', $calc_id ) ); ?>">
						<span v-text=" thankYouPage.custom_button_text"></span>
					</a>
				</div>
				<?php if ( isset( $invoice ) && $invoice['use_in_all'] ) : ?>
					<div v-if="thankYouPage.download_button">
						<button class="<?php echo esc_attr( apply_filters( 'ccb_confirmation_pdf_button_styles', 'calc-success', $calc_id ) ); ?>" @click.prevent="downloadPdf">
							<span class="ccb-ellipsis" v-text="thankYouPage.download_button_text"></span>
						</button>
					</div>
					<div v-if="thankYouPage.share_button">
						<button class="calc-secondary" @click.prevent="sendPdf">
							<span class="ccb-ellipsis" v-text="thankYouPage.share_button_text"></span>
						</button>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

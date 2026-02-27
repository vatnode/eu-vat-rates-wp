<?php defined( 'ABSPATH' ) || exit; ?>

<div class="wrap euvatr-wrap">
    <h1><?php esc_html_e( 'EU VAT Rates for WooCommerce', 'eu-vat-rates-woo' ); ?></h1>

    <?php if ( $synced === 'success' ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'EU VAT rates synced successfully.', 'eu-vat-rates-woo' ); ?></p>
        </div>
    <?php elseif ( $synced === 'error' ) : ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php esc_html_e( 'Sync failed.', 'eu-vat-rates-woo' ); ?>
                <?php if ( $last_err ) : ?>
                    <br><code><?php echo esc_html( $last_err ); ?></code>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ( ! $is_pro ) : ?>
    <!-- Upgrade banner (free users only) -->
    <div class="euvatr-upgrade-banner">
        <div class="euvatr-upgrade-banner__icon">⚡</div>
        <div class="euvatr-upgrade-banner__body">
            <strong><?php esc_html_e( 'Auto-sync is a Pro feature', 'eu-vat-rates-woo' ); ?></strong>
            <p>
                <?php esc_html_e( 'EU VAT rates change — Estonia alone raised its rate twice in two years. Pro syncs your WooCommerce tax table every day automatically, so you never charge the wrong rate.', 'eu-vat-rates-woo' ); ?>
            </p>
        </div>
        <a href="https://vatnode.dev/plugins/eu-vat-rates#upgrade" target="_blank" rel="noopener noreferrer" class="button button-primary euvatr-upgrade-banner__cta">
            <?php esc_html_e( 'Upgrade to Pro — $39/year', 'eu-vat-rates-woo' ); ?>
        </a>
    </div>
    <?php endif; ?>

    <!-- Status card -->
    <div class="euvatr-card">
        <h2><?php esc_html_e( 'Sync status', 'eu-vat-rates-woo' ); ?></h2>
        <table class="euvatr-status-table">
            <tr>
                <th><?php esc_html_e( 'Plan', 'eu-vat-rates-woo' ); ?></th>
                <td>
                    <?php if ( $is_pro ) : ?>
                        <span class="euvatr-badge euvatr-badge--pro"><?php esc_html_e( 'Pro', 'eu-vat-rates-woo' ); ?></span>
                    <?php else : ?>
                        <span class="euvatr-badge euvatr-badge--free"><?php esc_html_e( 'Free', 'eu-vat-rates-woo' ); ?></span>
                        &nbsp;
                        <a href="https://vatnode.dev/plugins/eu-vat-rates#upgrade" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e( 'Upgrade →', 'eu-vat-rates-woo' ); ?>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Auto-sync', 'eu-vat-rates-woo' ); ?></th>
                <td>
                    <?php if ( $is_pro ) : ?>
                        <span class="euvatr-status-active">&#10003; <?php esc_html_e( 'Active — daily at midnight UTC', 'eu-vat-rates-woo' ); ?></span>
                    <?php else : ?>
                        <span class="euvatr-status-inactive"><?php esc_html_e( 'Disabled', 'eu-vat-rates-woo' ); ?></span>
                        &nbsp;·&nbsp;
                        <a href="https://vatnode.dev/plugins/eu-vat-rates#upgrade" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e( 'Enable with Pro', 'eu-vat-rates-woo' ); ?>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Last sync', 'eu-vat-rates-woo' ); ?></th>
                <td><?php echo $last_sync ? esc_html( $last_sync ) : '<em>' . esc_html__( 'Never', 'eu-vat-rates-woo' ) . '</em>'; ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Data version', 'eu-vat-rates-woo' ); ?></th>
                <td><?php echo $last_ver ? esc_html( $last_ver ) : '—'; ?></td>
            </tr>
            <?php if ( $is_pro ) : ?>
            <tr>
                <th><?php esc_html_e( 'Next auto-sync', 'eu-vat-rates-woo' ); ?></th>
                <td><?php echo esc_html( $next_run ); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><?php esc_html_e( 'Source', 'eu-vat-rates-woo' ); ?></th>
                <td>
                    <a href="https://github.com/vatnode/eu-vat-rates-data" target="_blank" rel="noopener noreferrer">vatnode/eu-vat-rates-data</a>
                    <?php esc_html_e( '(European Commission TEDB)', 'eu-vat-rates-woo' ); ?>
                </td>
            </tr>
        </table>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
            <input type="hidden" name="action" value="euvatr_sync">
            <?php wp_nonce_field( 'euvatr_sync' ); ?>
            <?php submit_button( __( 'Sync now', 'eu-vat-rates-woo' ), 'secondary', 'submit', false ); ?>
        </form>
    </div>

    <!-- Rates table -->
    <?php if ( $data && ! empty( $data['rates'] ) ) : ?>
    <div class="euvatr-card">
        <h2>
            <?php
            printf(
                /* translators: %d = number of countries */
                esc_html__( 'Current rates (%d countries)', 'eu-vat-rates-woo' ),
                count( $data['rates'] )
            );
            ?>
        </h2>
        <table class="wp-list-table widefat fixed striped euvatr-rates-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Country', 'eu-vat-rates-woo' ); ?></th>
                    <th><?php esc_html_e( 'Code', 'eu-vat-rates-woo' ); ?></th>
                    <th><?php esc_html_e( 'Standard', 'eu-vat-rates-woo' ); ?></th>
                    <th><?php esc_html_e( 'Reduced', 'eu-vat-rates-woo' ); ?></th>
                    <th><?php esc_html_e( 'Super-reduced', 'eu-vat-rates-woo' ); ?></th>
                    <th><?php esc_html_e( 'Parking', 'eu-vat-rates-woo' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $data['rates'] as $code => $country ) : ?>
                <tr>
                    <td><?php echo esc_html( $country['country'] ); ?></td>
                    <td><code><?php echo esc_html( $code ); ?></code></td>
                    <td><?php echo esc_html( $country['standard'] . '%' ); ?></td>
                    <td>
                        <?php if ( ! empty( $country['reduced'] ) ) : ?>
                            <?php echo esc_html( implode( ', ', array_map( fn( $r ) => $r . '%', $country['reduced'] ) ) ); ?>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?php echo isset( $country['super_reduced'] ) ? esc_html( $country['super_reduced'] . '%' ) : '—'; ?></td>
                    <td><?php echo isset( $country['parking'] ) ? esc_html( $country['parking'] . '%' ) : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

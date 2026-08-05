<?php defined( 'ABSPATH' ) || exit; ?>

<div class="wrap euvatr-wrap">
    <h1><?php esc_html_e( 'EU VAT Rates & VAT Number Validation', 'eu-vat-rates-woo' ); ?></h1>

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

    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Settings saved.', 'eu-vat-rates-woo' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( $tested === 'success' ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html( $message ?: __( 'API key accepted by vatnode.', 'eu-vat-rates-woo' ) ); ?></p>
        </div>
    <?php elseif ( $tested === 'error' ) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html( $message ?: __( 'API key check failed.', 'eu-vat-rates-woo' ) ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( ! $has_key ) : ?>
    <div class="euvatr-upgrade-banner">
        <div class="euvatr-upgrade-banner__icon">&#9889;</div>
        <div class="euvatr-upgrade-banner__body">
            <strong><?php esc_html_e( 'Sell to EU businesses without VAT', 'eu-vat-rates-woo' ); ?></strong>
            <p>
                <?php esc_html_e( 'Add a vatnode API key and the plugin checks customer VAT numbers against the official VIES service at checkout. Verified EU business buyers outside your country are charged no VAT, and the evidence is stored on the order.', 'eu-vat-rates-woo' ); ?>
            </p>
        </div>
        <a href="<?php echo esc_url( $signup_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary euvatr-upgrade-banner__cta">
            <?php esc_html_e( 'Get an API Key', 'eu-vat-rates-woo' ); ?>
        </a>
    </div>
    <?php endif; ?>

    <!-- VAT number validation -->
    <div class="euvatr-card">
        <h2><?php esc_html_e( 'VAT number validation', 'eu-vat-rates-woo' ); ?></h2>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="euvatr_save_settings">
            <?php wp_nonce_field( 'euvatr_save_settings' ); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="euvatr-api-key"><?php esc_html_e( 'vatnode API key', 'eu-vat-rates-woo' ); ?></label>
                    </th>
                    <td>
                        <input
                            type="password"
                            id="euvatr-api-key"
                            name="api_key"
                            class="regular-text"
                            autocomplete="off"
                            placeholder="<?php echo $has_key ? esc_attr( $masked_key ) : 'vat_live_…'; ?>"
                        >
                        <p class="description">
                            <?php if ( $has_key ) : ?>
                                <?php esc_html_e( 'A key is stored. Leave this empty to keep it, or paste a new one to replace it.', 'eu-vat-rates-woo' ); ?>
                            <?php else : ?>
                                <?php
                                printf(
                                    /* translators: %s = link to vatnode */
                                    esc_html__( 'Create a key in your %s dashboard. The free plan includes a monthly request quota.', 'eu-vat-rates-woo' ),
                                    '<a href="' . esc_url( $signup_url ) . '" target="_blank" rel="noopener noreferrer">vatnode</a>'
                                );
                                ?>
                            <?php endif; ?>
                        </p>
                        <?php if ( $has_key ) : ?>
                            <p>
                                <strong><?php esc_html_e( 'Key status:', 'eu-vat-rates-woo' ); ?></strong>
                                <?php
                                $states = [
                                    'active'         => __( 'Working', 'eu-vat-rates-woo' ),
                                    'invalid'        => __( 'Rejected by vatnode', 'eu-vat-rates-woo' ),
                                    'quota_exceeded' => __( 'Monthly quota exceeded', 'eu-vat-rates-woo' ),
                                    'unreachable'    => __( 'Could not reach vatnode', 'eu-vat-rates-woo' ),
                                    'error'          => __( 'Last call failed', 'eu-vat-rates-woo' ),
                                    'unknown'        => __( 'Not checked yet', 'eu-vat-rates-woo' ),
                                ];
                                echo esc_html( $states[ $key_status['state'] ] ?? $key_status['state'] );
                                ?>
                                <?php if ( $key_status['message'] !== '' ) : ?>
                                    — <code><?php echo esc_html( $key_status['message'] ); ?></code>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Checkout', 'eu-vat-rates-woo' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="validation_enabled" value="1" <?php checked( $enabled ); ?>>
                            <?php esc_html_e( 'Verify VAT numbers with VIES and apply the reverse charge', 'eu-vat-rates-woo' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'The VAT number field is always shown at checkout. Without a key the number is only format-checked and stored — VAT is still charged.', 'eu-vat-rates-woo' ); ?>
                        </p>
                        <br>
                        <label>
                            <input type="checkbox" name="field_required" value="1" <?php checked( $required ); ?>>
                            <?php esc_html_e( 'Make the VAT number field required', 'eu-vat-rates-woo' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Only for B2B-only stores — consumers do not have a VAT number.', 'eu-vat-rates-woo' ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button( __( 'Save Settings', 'eu-vat-rates-woo' ), 'primary', 'submit', false ); ?>
            <?php if ( $has_key ) : ?>
                <button type="submit" name="remove_key" value="1" class="button button-link-delete">
                    <?php esc_html_e( 'Remove key', 'eu-vat-rates-woo' ); ?>
                </button>
            <?php endif; ?>
        </form>

        <?php if ( $has_key ) : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px">
                <input type="hidden" name="action" value="euvatr_test_key">
                <?php wp_nonce_field( 'euvatr_test_key' ); ?>
                <?php submit_button( __( 'Test Key', 'eu-vat-rates-woo' ), 'secondary', 'submit', false ); ?>
            </form>
        <?php endif; ?>

        <?php if ( $validation ) : ?>
            <p class="euvatr-status-active" style="margin-top:12px">
                &#10003; <?php esc_html_e( 'Reverse charge is active for verified EU business buyers outside your country.', 'eu-vat-rates-woo' ); ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Sync status -->
    <div class="euvatr-card">
        <h2><?php esc_html_e( 'Rate sync', 'eu-vat-rates-woo' ); ?></h2>
        <table class="euvatr-status-table">
            <tr>
                <th><?php esc_html_e( 'Auto-sync', 'eu-vat-rates-woo' ); ?></th>
                <td><span class="euvatr-status-active">&#10003; <?php esc_html_e( 'Active — daily', 'eu-vat-rates-woo' ); ?></span></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Last sync', 'eu-vat-rates-woo' ); ?></th>
                <td><?php echo $last_sync ? esc_html( $last_sync ) : '<em>' . esc_html__( 'Never', 'eu-vat-rates-woo' ) . '</em>'; ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Data version', 'eu-vat-rates-woo' ); ?></th>
                <td><?php echo $last_ver ? esc_html( $last_ver ) : '—'; ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Next auto-sync', 'eu-vat-rates-woo' ); ?></th>
                <td><?php echo esc_html( $next_run ); ?></td>
            </tr>
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
            <?php submit_button( __( 'Sync Now', 'eu-vat-rates-woo' ), 'secondary', 'submit', false ); ?>
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

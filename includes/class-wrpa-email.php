<?php
/**
 * WRPA - Email Module
 * @package WisdomRain\PremiumAccess
 */

namespace WRPA;

if ( ! defined( 'ABSPATH' ) ) exit;

class WRPA_Email {

    const META_VERIFIED = '_wrpa_email_verified';
    const META_TOKEN    = '_wrpa_email_token';

    /**
     * Registers the module hooks.
     *
     * @return void
     */
    public static function init() {
        add_action( 'init', [ __CLASS__, 'maybe_verify_account' ] );
    }

    /**
     * Sends an email verification link to the specified user.
     *
     * @param int $user_id User identifier.
     * @return bool Whether a verification email was attempted.
     */
    public static function send_verification( $user_id ) {
        $user_id = absint( $user_id );

        if ( ! $user_id ) {
            self::log( 'WRPA email verification skipped — invalid user id provided.' );
            return false;
        }

        $user = get_user_by( 'id', $user_id );

        if ( ! $user || empty( $user->user_email ) ) {
            self::log(
                'WRPA email verification skipped — user or email address missing.',
                [
                    'user_id' => $user_id,
                ]
            );
            return false;
        }

        $verified_flag = get_user_meta( $user_id, self::META_VERIFIED, true );

        if ( $verified_flag ) {
            self::log(
                'WRPA email verification not sent — user already verified.',
                [ 'user_id' => $user_id ]
            );
            return false;
        }

        if ( ! function_exists( 'wp_generate_password' ) ) {
            self::log( 'WRPA email verification failed — password generator unavailable.', [ 'user_id' => $user_id ] );
            return false;
        }

        $token = wp_generate_password( 32, false, false );

        update_user_meta( $user_id, self::META_TOKEN, $token );
        update_user_meta( $user_id, self::META_VERIFIED, '' );

        $verify_url = add_query_arg( 'wrpa_verify', $token, home_url( '/' ) );

        $subject = __( 'Confirm your email address', 'wrpa' );
        /* translators: %s: verification URL */
        $message = sprintf(
            __( "Please confirm your email address by visiting the following link: %s", 'wrpa' ),
            esc_url( $verify_url )
        );

        self::log(
            'WRPA email verification generated.',
            [
                'user_id'    => $user_id,
                'token_hash' => md5( $token ),
            ]
        );

        $sent = wp_mail( $user->user_email, $subject, $message );

        if ( $sent ) {
            self::log(
                'WRPA email verification email dispatched.',
                [
                    'user_id' => $user_id,
                    'email'   => $user->user_email,
                ]
            );
        } else {
            self::log(
                'WRPA email verification email failed to send.',
                [
                    'user_id' => $user_id,
                    'email'   => $user->user_email,
                ]
            );
        }

        return $sent;
    }

    /**
     * Attempts to validate a verification request from the current request.
     *
     * @return void
     */
    public static function maybe_verify_account() {
        if ( empty( $_GET['wrpa_verify'] ) ) {
            return;
        }

        $raw_token = wp_unslash( $_GET['wrpa_verify'] );
        $token     = sanitize_text_field( $raw_token );

        if ( '' === $token ) {
            self::log( 'WRPA email verification rejected — token missing from request.' );
            return;
        }

        $users = get_users(
            [
                'meta_key'   => self::META_TOKEN,
                'meta_value' => $token,
                'fields'     => 'ids',
                'number'     => 1,
            ]
        );

        if ( empty( $users ) ) {
            self::log(
                'WRPA email verification rejected — no matching token found.',
                [ 'token_hash' => md5( $token ) ]
            );
            return;
        }

        $user_id = (int) $users[0];

        update_user_meta( $user_id, self::META_VERIFIED, '1' );
        delete_user_meta( $user_id, self::META_TOKEN );

        self::log(
            'WRPA email verification confirmed.',
            [ 'user_id' => $user_id ]
        );
    }

    /**
     * Proxy logging helper so the email module can share WRPA logging.
     *
     * @param string $message Log entry message.
     * @param array  $context Additional context data.
     * @return void
     */
    protected static function log( $message, array $context = [] ) {
        if ( class_exists( '\\WRPA\\WRPA_Access' ) && method_exists( '\\WRPA\\WRPA_Access', 'log' ) ) {
            \WRPA\WRPA_Access::log( $message, $context );
            return;
        }

        if ( ! empty( $context ) ) {
            $context_string = function_exists( 'wp_json_encode' ) ? wp_json_encode( $context ) : json_encode( $context );

            if ( $context_string ) {
                $message .= ' ' . $context_string;
            }
        }

        if ( function_exists( 'error_log' ) ) {
            error_log( $message );
        }
    }
}

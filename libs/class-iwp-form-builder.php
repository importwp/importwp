<?php

class IWP_FormBuilder {

	static $validation = array();
	static $errors = array();
	static $name = false;
	static $config;
	static $has_posted = false;
	static $prefix = 'jc-importer_';
	private static $complete = false;

	static function init( &$config ) {
		self::$config = $config;
	}

	static function get_prefix() {
		return self::$prefix;
	}

	static function process_form( $name ) {
		self::$name = $name;

		// run validation if exists
		if ( isset( self::$config[ $name ]['validation'] ) ) {
			self::set_validation_rules( self::$config[ $name ]['validation'] );
		}

		self::process();
	}

	/**
	 * Set Form Validation Rules
	 *
	 * Set all form validation rules to the same format
	 *
	 * @param array $validation_fields
	 *
	 * @return  void
	 */
	static function set_validation_rules( $validation_fields = array() ) {
		// set all validation rules to the same format
		if ( isset( $validation_fields ) ) {
			if ( is_array( $validation_fields ) ) {
				foreach ( $validation_fields as $field => $rules ) {

					$field_rules = array();

					if ( ! is_array( $rules ) ) {
						continue;
					}

					// is single rule
					if ( isset( $rules['rule'] ) ) {
						$rule          = $rules;
						$field_rules[] = $rules;
					} else {
						foreach ( $rules as $rule ) {
							if ( ! isset( $rule['rule'] ) ) {
								continue;
							}
							$field_rules[] = $rule;
						}
					}

					$fields[ $field ] = array_reverse( $field_rules );
				}
			}
		}

		self::$validation = $fields;
	}

	static function process() {
		if ( isset( $_POST[ self::$prefix . 'form_action' ] ) ) {
			self::$has_posted = true;
		}

		if ( self::$has_posted ) {
			self::run_validation();

			$field_name = strtolower( self::$name );
			if ( ! isset( $_POST[ self::$prefix . 'secret_' . $field_name ] ) || ! wp_verify_nonce( $_POST[ self::$prefix . 'secret_' . $field_name ], self::$prefix . 'secret_' . $field_name ) ) {
				self::$errors[] = 'Sorry but your form could not be submitted';
			}

			if ( empty( self::$errors ) ) {
				self::$complete = true;
			}
		}
	}

	static function run_validation() {
		$validation = self::$validation;

		foreach ( $validation as $field_name => $rules ) {
			foreach ( $rules as $rule ) {

				if ( ! isset( $rule['rule'] ) ) {
					continue;
				}

				$args_count = count( $rule['rule'] ) - 1;

				if ( $args_count == - 1 ) {
					continue;
				}

				$validation_rule = $rule['rule'][0];
				if ( isset( $rule['type'] ) && $rule['type'] == 'file' ) {
					$validation_field = $_FILES[ self::$prefix . $field_name ]['name'];
				} else {
					$validation_field = $_POST[ self::$prefix . $field_name ];
				}

				$validation_message = $rule['message'];

				switch ( $args_count ) {
					case 0:
						if ( ! FormValidator::$validation_rule( $validation_field ) ) {
							self::$errors[ $field_name ] = $validation_message;
							continue;
						}
						break;
					case 1:
						if ( ! FormValidator::$validation_rule( $validation_field, $rule['rule'][1] ) ) {
							self::$errors[ $field_name ] = $validation_message;
							continue;
						}
						break;
				}
			}
		}

	}

	static function is_complete() {
		if ( self::$complete == true ) {
			return true;
		}

		return false;
	}

	static function create( $name, $args = array() ) {
		$title     = false;
		$desc      = false;
		$error_msg = isset( self::$errors['message'] ) ? self::$errors['message'] : 'There are some errors with this form.';
		$output    = '';

		if ( isset( $args['title'] ) ) {
			$title = $args['title'];
		}

		if ( isset( $args['desc'] ) ) {
			$desc = $args['desc'];
		}

		$output .= '<div class="form form_wrapper">';

		if ( $title || $desc ) {
			$output .= '<div class="form_header">';

			if ( $title ) {
				$output .= '<h1>' . $title . '</h1>';
			}
			if ( $desc ) {
				$output .= '<p>' . $desc . '</p>';
			}

			$output .= '</div>';
		}

		if ( ! empty( self::$errors ) ) {
			$output .= '<div id="message" class="error_msg warn error below-h2"><p>' . $error_msg . '</p></div>';
		}

		if ( isset( $args['type'] ) && $args['type'] == 'file' ) {
			$output .= '<form method="POST" action="" class="" enctype="multipart/form-data">';
		} else {
			$output .= '<form method="POST" action="" class="">';
		}


		$output     .= self::hidden( 'form_action', array( 'value' => $name ) );
		$field_name = strtolower( $name );
		$output     .= wp_nonce_field( self::$prefix . 'secret_' . $field_name, self::$prefix . 'secret_' . $field_name, true, false );

		return $output;
	}

	static function hidden( $name, $args = array() ) {
		$value = '';
		if ( isset( $args['value'] ) ) {
			$value = $args['value'];
		}
		if ( isset( $args['default'] ) ) {
			$value = $args['default'];
		}

		$id = self::get_id( self::$prefix . $name );

		$classes = array();
		if(isset($args['class'])){
			$classes[] = $args['class'];
		}

		$value  = self::get_value( $name, $value );
		$output = '<div class="input input--hidden '.implode(' ', $classes).'">' .
		          '<input type="hidden" name="' . self::$prefix . $name . '" id="' . $id . '" value="' . $value . '" />' .
		          '</div>';

		return $output;
	}

	static function get_value( $field, $default = false ) {
		return isset( $_POST[ self::$prefix . $field ] ) ? $_POST[ self::$prefix . $field ] : $default;
	}

	static function password( $name, $args = array() ) {
		$args['type'] = 'password';

		return self::text( $name, $args );

	}

	static function text( $name, $args = array() ) {
		// set default arg values
		$required = false;
		$label    = $name;
		$type     = 'text';
		$default  = '';
		$after    = null;
		$data     = array();
		$tooltip  = '';
		$wrapper_data = array();
		extract( $args );

		$data_str = '';
		if ( is_array( $data ) && ! empty( $data ) ) {
			foreach ( $data as $dk => $dv ) {
				$data_str .= ' data-' . $dk . '="' . $dv . '"';
			}
		}

		$wrapper_data_str = '';
		if ( is_array( $wrapper_data ) && ! empty( $wrapper_data ) ) {
			foreach ( $wrapper_data as $dk => $dv ) {
				$wrapper_data_str .= ' data-' . $dk . '="' . $dv . '"';
			}
		}

		if ( $type != 'password' ) {
			$type = 'text';
		}

		$value   = self::get_value( $name, $default );
		$error   = self::get_error( $name );
		$classes = array( 'input', 'text' );

		if ( $error ) {
			$classes[] = 'form-error';
		}

		if ( ! empty( $class ) ) {
			if ( ! is_array( $class ) ) {
				$classes[] = $class;
			}
		}

		if ( $required == true ) {
			if ( empty( $value ) && self::$has_posted == true ) {
				$classes[] = 'form-error';
			}

			$classes[] = 'form-required';
		}

		$output = '<div class="' . implode( ' ', $classes ) . '" '.$wrapper_data_str.' />';

		if ( $label !== false ) {
			$output .= self::get_label( $label, $tooltip );
		}

		$id = self::get_id( self::$prefix . $name );

		$output .= '<input aria-label="'.esc_attr(strip_tags($label)).'" type="' . $type . '" name="' . self::$prefix . $name . '" id="' . $id . '" value="' . $value . '" ' . $data_str . ' />';

		if ( $after ) {
			$output .= $after;
		}

		if ( $error ) {
			$output .= $error;
		}

		$output .= '</div>';

		return $output;
	}

	static function get_error( $name ) {
		if ( isset( self::$errors[ $name ] ) && ! empty( self::$errors[ $name ] ) ) {
			return '<div class="validation_msg"><p>&uarr; ' . self::$errors[ $name ] . '</p></div>';
		}

		return false;
	}

	static function get_label( $name, $tooltip = '' ) {
		$tooltip_str   = '';
		$label_classes = array( 'iwp-field__label' );
		if ( ! empty( $tooltip ) ) {
			$label_classes[] = 'iwp-field__label--has_tooltip';
			$tooltip_str     = '<span class="iwp-field__tooltip iwp-field__tooltip--inline" data-title="' . esc_attr( $tooltip ) . '" title="' . esc_attr( $tooltip ) . '">?</span>';
		}
		$output = '<label class="' . implode( ' ', $label_classes ) . '">' . $name . $tooltip_str . '</label>';

		return $output;
	}

	static function get_id( $id ) {

		$id = str_replace( array( '[', ']' ), array( '-', '' ), $id );

		return $id;
	}

	static function file( $name, $args = array() ) {
		$label    = isset( $args['label'] ) ? $args['label'] : $name;
		$required = false;
		$classes  = array( 'input', 'text' );
		extract( $args );
		$error = self::get_error( $name );

		if ( $error ) {
			$classes[] = 'form-error';
		}

		if ( ! empty( $class ) ) {
			if ( ! is_array( $class ) ) {
				$classes[] = $class;
			}
		}

		if ( $required == true ) {
			if ( empty( $value ) && self::$has_posted == true ) {
				$classes[] = 'form-error';
			}

			$classes[] = 'form-required';
		}

		$output = '<div class="' . implode( ' ', $classes ) . '" />';

		if ( $label !== false ) {
			$output .= self::get_label( $label );
		}

		$output .= '<input aria-label="'.esc_attr(strip_tags($label)).'" type="file" name="' . self::$prefix . $name . '" id="' . self::$prefix . $name . '" />';

		if ( $error ) {
			$output .= $error;
		}

		$output .= '</div>';

		return $output;
	}

	static function textarea( $name, $args = array() ) {
		// set default arg values
		$required = false;
		$label    = $name;
		$default  = '';
		extract( $args );

		$value   = self::get_value( $name, $default );
		$error   = self::get_error( $name );
		$classes = array( 'input', 'textarea' );

		if ( $error ) {
			$classes[] = 'form-error';
		}

		if ( ! empty( $class ) ) {
			if ( ! is_array( $class ) ) {
				$classes[] = $class;
			}
		}

		if ( $required == true ) {
			if ( empty( $value ) && self::$has_posted == true ) {
				$classes[] = 'form-error';
			}

			$classes[] = 'form-required';
		}

		$output = '<div class="' . implode( ' ', $classes ) . '" />';

		if ( $label !== false ) {
			$output .= self::get_label( $label );
		}

		$output .= '<textarea  aria-label="'.esc_attr(strip_tags($label)).'" name="' . self::$prefix . $name . '" id="' . self::$prefix . $name . '" >' . $value . '</textarea>';

		if ( $error ) {
			$output .= $error;
		}

		$output .= '</div>';

		return $output;
	}

	static function wysiwyg( $name, $args = array() ) {
		// set default arg values
		$required = false;
		$label    = $name;
		$default  = '';
		extract( $args );

		$value   = self::get_value( $name, $default );
		$error   = self::get_error( $name );
		$classes = array( 'input', 'wysiwyg' );

		if ( $error ) {
			$classes[] = 'form-error';
		}

		if ( ! empty( $class ) ) {
			if ( ! is_array( $class ) ) {
				$classes[] = $class;
			}
		}

		if ( $required == true ) {
			if ( empty( $value ) && self::$has_posted == true ) {
				$classes[] = 'form-error';
			}

			$classes[] = 'form-required';
		}

		$output = '<div class="' . implode( ' ', $classes ) . '" />';

		if ( $label !== false ) {
			$output .= self::get_label( $label );
		}

		// $output .= '<textarea name="'.self::$prefix.$name.'" id="'.self::$prefix.$name.'" >'.$value.'</textarea>';
		ob_start();
		$editor_id = self::$prefix . $name; // 'SupportResponse';
		$settings  = array(
			'wpautop'       => false, // use wpautop?
			'media_buttons' => false, // show insert/upload button(s)
			'textarea_rows' => 10,
			'teeny'         => false, // output the minimal editor config used in Press This
			'tinymce'       => false
		);
		wp_editor( '', $editor_id, $settings );
		$output .= ob_get_contents();
		ob_end_clean();


		if ( $error ) {
			$output .= $error;
		}

		$output .= '</div>';

		return $output;
	}

	static function select( $name, $args = array() ) {
		// set default arg values
		$required = false;
		$default  = 0;
		$label    = $name;
		$options  = array();
		$empty    = false;
		$tooltip  = '';
		$id       = false;
		extract( $args );

		$value   = self::get_value( $name );
		$error   = self::get_error( $name );
		$classes = array( 'input', 'select' );

		if ( $error ) {
			$classes[] = 'form-error';
		}

		if ( ! empty( $class ) ) {
			if ( ! is_array( $class ) ) {
				$classes[] = $class;
			}
		}

		if ( $required == true ) {
			if ( empty( $value ) && self::$has_posted == true ) {
				$classes[] = 'form-error';
			}

			$classes[] = 'form-required';
		}

		$id_string = '';
		if ( $id ) {
			$id_string = ' id="' . $id . '"';
		}

		$output = '<div class="' . implode( ' ', $classes ) . '" ' . $id_string . ' />';

		if ( $label !== false ) {
			$output .= self::get_label( $label, $tooltip );
		}

		$output .= '<select aria-label="'.esc_attr(strip_tags($label)).'" name="' . self::$prefix . $name . '" id="' . self::get_id( self::$prefix . $name ) . '">';

		if ( $empty ) {
			$empty_val = $empty === true ? '' : $empty;
			$output    .= '<option value="">' . $empty_val . '</option>';
		}

		foreach ( $options as $option_id => $option ) {
			if ( ( $option_id === $default && empty( $value ) ) || $option_id === $value ) {
				$output .= '<option value="' . $option_id . '" selected="selected">' . $option . '</option>';
			} else {
				$output .= '<option value="' . $option_id . '">' . $option . '</option>';
			}
		}

		$output .= '</select>';

		if ( $error ) {
			$output .= $error;
		}

		$output .= '</div>';


		return $output;
	}

	static function radio( $name, $args = array() ) {

		$classes = array( 'input', 'radio' );
		$value   = isset( $args['value'] ) ? $args['value'] : false;
		$label   = isset( $args['label'] ) ? $args['label'] : false;
		$default = isset( $args['checked'] ) ? $args['checked'] : false;

		// get checked vars

		$checked = self::get_value( $name );
		if ( $checked == $value || ( $default == true && ( empty( $checked ) || $checked == 'false' ) ) ) {
			$checked = ' checked="checked"';
		} else {
			$checked = '';
		}

		if ( isset( $args['class'] ) && ! empty( $args['class'] ) ) {
			$classes = array_merge( $classes, explode( ' ', $args['class'] ) );
		}

		$output = '<div class="' . implode( ' ', $classes ) . '" >';

		$output .= '<input aria-label="'.esc_attr(strip_tags($label)).'" type="radio" name="' . self::$prefix . $name . '" value="' . $value . '" ' . $checked . ' />';

		if ( $label !== false ) {
			$output .= self::get_label( $label );
		}

		$output .= '</div>';

		return $output;
	}

	static function checkbox( $name, $args = array() ) {
		$output   = '';
		$label    = $name;
		$required = false;
		$checked  = true;
		$after    = null;
		extract( $args );
		$error   = self::get_error( $name );
		$classes = array( 'input', 'support-checkbox' );

		if ( $error ) {
			$classes[] = 'form-error';
		}

		if ( ! empty( $class ) ) {
			if ( ! is_array( $class ) ) {
				$classes[] = $class;
			}
		}

		if ( $required == true ) {
			if ( empty( $value ) && self::$has_posted == true ) {
				$classes[] = 'form-error';
			}
			$classes[] = 'form-required';
		}

		$output = '<div class="' . implode( ' ', $classes ) . '" >';
		if ( $checked ) {
			$output .= '<input aria-label="'.esc_attr(strip_tags($label)).'" type="checkbox" name="' . self::$prefix . $name . '" value="1" id="' . self::$prefix . $name . '" checked="checked" />';
		} else {
			$output .= '<input aria-label="'.esc_attr(strip_tags($label)).'" type="checkbox" name="' . self::$prefix . $name . '" value="1" id="' . self::$prefix . $name . '" />';
		}

		if ( $label !== false ) {
			$output .= self::get_label( $label );
		}

		if ( $after ) {
			$output .= $after;
		}

		if ( $error ) {
			$output .= $error;
		}
		$output .= '</div>';

		return $output;
	}

	static function end( $submit = '', $args = array() ) {
		$output = '';
		if ( $submit != '' ) {
			$output .= self::submit( $submit, $args );
		}

		$output .= '</form>';
		$output .= '</div>';

		return $output;
	}

	static function submit( $name, $args = array() ) {
		$args_class = isset( $args['class'] ) ? $args['class'] : '';
		$value      = isset( $args['value'] ) ? $args['value'] : $name;

		$id = self::get_id( self::$prefix . $name );

		$output = '<div class="input submit">';
		$output .= '<input aria-label="'.esc_attr(strip_tags($value)).'" type="submit" name="' . self::$prefix . $name . '" value="' . $value . '" class="' . $args_class . '" id="' . $id . '-' . strtolower( $value ) . '" />';
		$output .= '</div>';

		return $output;
	}

	static function set_error( $error ) {
		self::$errors['message'] = $error;
		self::$complete          = false;
	}
}

/**
 * Form Validator
 *
 * form field validation rules
 */
class FormValidator {

	static function min_length( $string = '', $size = 0 ) {
		if ( strlen( $string ) >= $size ) {
			return true;
		}

		return false;
	}

	static function max_length( $string = '', $size = 0 ) {
		if ( strlen( $string ) <= $size ) {
			return true;
		}

		return false;
	}

	static function required( $string ) {
		if ( ! empty( $string ) ) {
			return true;
		}

		return false;
	}

	static function match( $string, $field ) {
		if ( $string == $_POST[ IWP_FormBuilder::get_prefix() . $field ] ) {
			return true;
		}

		return false;
	}

	static function notEqual( $string, $value ) {
		if ( $string !== $value ) {
			return true;
		}

		return false;
	}

	static function email( $string = '' ) {
		if ( is_email( $string ) ) {
			return true;
		}

		return false;
	}
}


?>
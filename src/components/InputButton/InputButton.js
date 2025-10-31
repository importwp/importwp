import './InputButton.scss';

export default function InputButton({
    type = 'button',
    theme = 'primary',
    onClick = () => { },
    disabled = false,
    loading = false,
    children
}) {
    return (
        <button
            disabled={disabled}
            className={`iwp-input-button iwp-input-button--${theme}`}
            type={type}
            onClick={onClick}>
            {loading && <span className="spinner is-active"></span>}
            {children}
        </button>
    );
}
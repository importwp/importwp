import './FormRow.scss';

export default function FormRow({ children }) {

    return (
        <div className="iwp-form__grid-flex">
            {children}
        </div>
    )
}
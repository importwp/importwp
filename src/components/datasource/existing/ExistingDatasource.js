import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { importer } from '../../../services/importer.service';

class ExistingDatasource extends Component {

    constructor(props) {
        super(props);

        this.state = {
            file: props.file
        };

        this.onChange = this.onChange.bind(this);
        this.run = this.run.bind(this);
    }

    onChange(event) {
        this.setState({ [event.target.name]: event.target.value });
    }

    run(callback = () => { }) {
        importer.save({ id: this.props.id, existing_id: this.state.file }).then(() => {
            callback();
        }, error => {
            this.props.onError(error);
        });
    }

    componentDidUpdate(prevProps) {
        if (prevProps.file !== this.props.file) {
            this.setState({ file: this.props.file });
        }
    }

    render() {
        const { files } = this.props;
        const { file } = this.state;
        return (
            <React.Fragment>
                {files && Object.keys(files).length > 0 && (
                    <div className="iwp-file-list">
                        <ul>
                            {Object.keys(files).map(file_id => (
                                <li key={file_id}>
                                    <input
                                        id={'file_' + file_id}
                                        type="radio"
                                        name="file"
                                        value={file_id}
                                        onChange={this.onChange}
                                        checked={file_id == file}
                                    />
                                    <label htmlFor={'file_' + file_id}>
                                        {files[file_id]}
                                    </label>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </React.Fragment>
        );
    }
}

ExistingDatasource.propTypes = {
    id: PropTypes.number,
    files: PropTypes.array,
    file: PropTypes.number,
    onError: PropTypes.func
};

ExistingDatasource.defaultProps = {
    onError: () => { }
};

export default ExistingDatasource;
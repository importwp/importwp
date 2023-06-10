import React from 'react';
import ImporterListItem from '../importer-list-item/ImporterListItem';
import { importer } from '../../services/importer.service';
import NoticeList from '../notice-list/NoticeList';
import { Link } from 'react-router-dom';
import GlobalNotice from '../global-notice/GlobalNotice';

const AJAX_BASE = window.iwp.admin_base;

class ArchivePage extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      loaded: false,
      errors: [],
      importers: [],
      status: [],
      init: false,
    };

    this.statusXHR = null;

    this.getImporters = this.getImporters.bind(this);
    this.getStatus = this.getStatus.bind(this);
    this.onDelete = this.onDelete.bind(this);
  }

  getImporters() {
    importer
      .importers()
      .then((data) => {
        this.setState({
          importers: data,
          loaded: true,
          init: true,
        });

        if (this.statusXHR !== null) {
          this.statusXHR.abort();
        }
        this.getStatus();
      })
      .catch((data) => {
        if (data.statusText === 'abort') {
          return;
        }

        this.setState({
          errors: [
            ...this.state.errors,
            {
              section: 'archive',
              message: data.responseJSON.message,
            },
          ],
          loaded: true,
          init: true,
        });
      });
  }

  onDelete(id) {
    this.setState({
      importers: this.state.importers.filter((data) => data.id !== id),
    });
    this.getImporters();
  }

  getStatus() {
    this.statusXHR = importer.status();
    this.statusXHR.request.subscribe(
      (response) => {
        this.setState({ status: response });
      },
      () => { }
    );
  }

  componentDidMount() {
    this.getImporters();
  }

  componentWillUnmount() {
    importer.abort('importers');

    if (this.statusXHR) {
      this.statusXHR.abort();
    }
  }

  render() {
    const { importers, status, init } = this.state;

    if (init === false) {
      return <NoticeList notices={[{ message: 'Loading', type: 'info' }]} />;
    }

    return (
      <React.Fragment>

        <GlobalNotice />

        <div className="iwp-archive-header">
          <Link to={AJAX_BASE + '&new'} className="iwp-add-new">
            Add Importer +
          </Link>
        </div>
        {importers.length > 0 &&
          importers.map((importer) => (
            <ImporterListItem
              key={importer.id}
              importer={importer}
              status={
                Array.isArray(status)
                  ? status.find((item) => {
                    return item?.version == 2 ? item.importer == importer.id : item.id === importer.id;
                  })
                  : {}
              }
              onDelete={this.onDelete}
            />
          ))}
        {importers.length === 0 && (
          <NoticeList
            notices={[
              {
                message:
                  'No Importers have been created, click add importer to create one.',
                type: 'info',
              },
            ]}
          />
        )}
      </React.Fragment>
    );
  }
}

export default ArchivePage;

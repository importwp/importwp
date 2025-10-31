import React, { useEffect, useState } from 'react';
import { importer } from '../../services/importer.service';
import NoticeList from '../notice-list/NoticeList';

const ToolsPage = () => {
  const [importers, setImporters] = useState([]);
  const [importerSelection, setImporterSelection] = useState([]);
  const [loading, setLoading] = useState(true);

  // importer
  const [file, setFile] = useState(null);
  const [importMessage, setImportMessage] = useState();

  useEffect(() => {
    const setup = async () => {
      try {
        setImporters(await importer.importers());
      } catch (e) {
        setImportMessage({
          message: 'Error: ' + e,
          type: 'error',
        });
      }
      setLoading(false);
    };

    setup();
  }, []);

  const selectionContainsId = (id) => {
    return importerSelection.indexOf(id) > -1;
  };

  const onCheckboxChecked = (e) => {
    const id = parseInt(e.target.value);
    const existingInSelection = selectionContainsId(id);

    if (e.target.checked === true && !existingInSelection) {
      let tmp = [...importerSelection, id];
      setImporterSelection(tmp);
    } else if (e.target.checked === false && existingInSelection) {
      let tmp = [...importerSelection];
      tmp.splice(tmp.indexOf(id));
      setImporterSelection(tmp);
    }
  };

  const doImport = async () => {
    setImportMessage({ message: 'Importing', type: 'info' });

    let form_data = new FormData();
    form_data.append('file', file);
    form_data.append('action', 'file_upload');
    try {
      const result = await importer.toolImport(form_data);
      setImportMessage({ message: result, type: 'success' });
    } catch (e) {
      setImportMessage({
        message: 'Error: ' + e,
        type: 'error',
      });
    }
  };

  return (
    <React.Fragment>
      <div className="iwp-form__grid">
        <div className="iwp-form__col iwp-form__col--first">
          <div className="iwp-form iwp-form--mb">
            <p className="iwp-heading">Export Importers</p>
            <p>
              Select the importers you would like to export, clicking the export
              file button will generate a json file that can be imported into
              another Import WP installation.
            </p>

            <p>
              <strong>Select Importers</strong>
            </p>

            <ul>
              {loading && (
                <NoticeList notices={[{ message: 'Loading', type: 'info' }]} />
              )}
              {importers &&
                importers.map((item) => (
                  <li key={item.id}>
                    <input
                      type="checkbox"
                      value={item.id}
                      checked={selectionContainsId(item.id)}
                      onChange={onCheckboxChecked}
                    />
                    <label>{item.name}</label>
                  </li>
                ))}
            </ul>
          </div>

          <div className="iwp-form__actions">
            <div className="iwp-buttons">
              <button
                className="button button-primary"
                type="button"
                onClick={() => importer.toolExport(importerSelection)}
                disabled={importerSelection.length === 0}
              >
                Export File
              </button>
            </div>
          </div>
        </div>
        <div className="iwp-form__col iwp-form__col--last">
          <div className="iwp-form iwp-form--mb">
            <p className="iwp-heading">Import Importers</p>
            <p>
              Select the Import WP JSON file you would like to import, clicking
              the import file button below will import the importers.
            </p>
            {importMessage && <NoticeList notices={[importMessage]} />}
            <input type="file" onChange={(e) => setFile(e.target.files[0])} />
          </div>

          <div className="iwp-form__actions">
            <div className="iwp-buttons">
              <button
                className="button button-primary"
                type="button"
                onClick={doImport}
                disabled={!file}
              >
                Import File
              </button>
            </div>
          </div>
        </div>
      </div>
    </React.Fragment>
  );
};

export default ToolsPage;

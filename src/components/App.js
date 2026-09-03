import React, { useState } from 'react';
import './App.scss';
import PageHeader from './page-header/PageHeader';
import qs from 'qs';
import ArchivePage from './archive-page/ArchivePage';
import EditPage from './edit-page/EditPage';
import SettingsPage from './settings-page/SettingsPage';
import PremiumPage from './premium-page/PremiumPage';
import SetupWizard from './setup-wizard/SetupWizard';
import ExporterEdit from './exporter-form/ExporterEdit';
import ExporterArchive from './exporter-form/ExporterArchive';

const IS_SETUP = window.iwp.is_setup;
const IS_PRO = window.iwp.is_pro === 'yes' ? true : false;
const IWP_TEMPLATES = window.iwp.templates;

function App(props) {
  const [isSetup, setIsSetup] = useState(IS_SETUP === 'no' ? false : true);

  const getActiveSection = () => {
    const values = qs.parse(props.location.search);
    if (typeof values.new !== 'undefined') {
      return 'new';
    } else if (typeof values['new-exporter'] !== 'undefined') {
      return 'new-exporter';
    } else if (typeof values.edit !== 'undefined') {
      return 'edit';
    } else if (typeof values['edit-exporter'] !== 'undefined') {
      return 'edit-exporter';
    } else if (typeof values.tab !== 'undefined') {
      const { tab } = values;
      if (tab === 'settings') {
        return 'settings';
      } else if (tab === 'premium') {
        return 'premium';
      } else if (tab === 'exporters') {
        return 'exporters';
      }
    }
    return 'archive';
  };

  const getPage = (section) => {
    const values = qs.parse(props.location.search);
    switch (section) {
      case 'new':
      case 'edit':
        const id =
          typeof values.edit !== 'undefined' ? parseInt(values.edit) : null;
        return <EditPage id={id} pro={IS_PRO} templates={IWP_TEMPLATES} />;
      case 'settings':
        return <SettingsPage />;
      case 'premium':
        return <PremiumPage />;
      case 'new-exporter':
      case 'edit-exporter':
        const exporterId =
          typeof values['edit-exporter'] !== 'undefined'
            ? parseInt(values['edit-exporter'])
            : null;
        return <ExporterEdit id={exporterId} pro={IS_PRO} />;
      case 'exporters':
        return <ExporterArchive />;
      default:
        return <ArchivePage />;
    }
  };

  const onSetupComplete = () => {
    setIsSetup(true);
  };

  const active = getActiveSection();

  if (isSetup === false) {
    return <SetupWizard onComplete={onSetupComplete} />;
  }

  return (
    <React.Fragment>
      <PageHeader active={active} pro={IS_PRO} />
      <div className="iwp-body">{getPage(active)}</div>
    </React.Fragment>
  );
}

export default App;

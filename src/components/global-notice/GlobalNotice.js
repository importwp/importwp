import React from 'react';
import NoticeList from '../notice-list/NoticeList';

const GlobalNotice = function () {

    const notices = window.iwp.hooks.applyFilters('iwp_global_notices', []);

    if (notices.length == 0) {
        return null;
    }

    return <NoticeList notices={notices} />;
};

export default GlobalNotice;
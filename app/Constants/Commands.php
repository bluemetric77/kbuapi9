<?php
namespace App\Constants;

class Commands
{
    // Control
    public const COMMAND_CONTROL_REBOOT = "REBOOT";
    public const COMMAND_CONTROL_UNLOCK = "AC_UNLOCK";
    public const COMMAND_CONTROL_UNALARM = "AC_UNALARM";
    public const COMMAND_CONTROL_INFO = "INFO";

    // Update
    public const COMMAND_UPDATE_USER_INFO = "DATA UPDATE USERINFO PIN={0}\tName={1}\tPri={2}\tPasswd={3}\tCard={4}\tGrp={5}\tTZ={6}";
    public const COMMAND_UPDATE_ID_CARD = "";
    public const COMMAND_UPDATE_FINGER_TMP = "DATA UPDATE FINGERTMP PIN={0}\tFID={1}\tSize={2}\tValid={3}\tTMP={4}";
    public const COMMAND_UPDATE_FACE_TMP = "DATA UPDATE FACE PIN={0}\tFID={1}\tValid={2}\tSize={3}\tTMP={4}";
    public const COMMAND_UPDATE_FVEIN = "DATA$ UPDATE FVEIN Pin={0}\tFID={1}\tIndex={2}\tValid={3}\tSize={4}\tTmp={5}";
    public const COMMAND_UPDATE_BIO_DATA = "DATA UPDATE BIODATA Pin={0}\tNo={1}\tIndex={2}\tValid={3}\tDuress={4}\tType={5}\tMajorVer={6}\tMinorVer={7}\tFormat={8}\tTmp={9}";
    public const COMMAND_UPDATE_BIO_PHOTO = "DATA UPDATE BIOPHOTO PIN={0}\tType={1}\tSize={2}\tContent={3}\tFormat={4}\tUrl={5}\tPostBackTmpFlag={6}";
    public const COMMAND_UPDATE_USER_PIC = "DATA UPDATE USERPIC PIN={0}\tSize={1}\tContent={2}";
    public const COMMAND_UPDATE_SMS = "DATA UPDATE SMS MSG={0}\tTAG={1}\tUID={2}\tMIN={3}\tStartTime={4}";
    public const COMMAND_UPDATE_USER_SMS = "DATA UPDATE USER_SMS PIN={0}\tUID={1}";
    public const COMMAND_UPDATE_AD_PIC = "DATA UPDATE ADPIC Index={0}\tSize={1}\tExtension={2}\tContent={3}";
    public const COMMAND_UPDATE_WORK_CODE = "DATA UPDATE WORKCODE PIN={0}\tCODE={1}\tNAME={2}";
    public const COMMAND_UPDATE_SHORTCUT_KEY = "DATA UPDATE ShortcutKey KeyID={0}\tKeyFun={1}\tStatusCode=={2}\tShowName={3}\tAutoState={4}\tAutoTime={5}\tSun={6}\tMon={7}\tTue={8}\tWed={9}\tThu={10}\tFri={11}\tSat={12}";
    public const COMMAND_UPDATE_ACC_GROUP = "DATA UPDATE AccGroup ID={0}\tVerify={1}\tValidHoliday={2}\tTZ={3}";
    public const COMMAND_UPDATE_ACC_TIME_ZONE = "DATA UPDATE AccTimeZone UID={0}\tSunStart={1}\tSunEnd={2}\tMonStart={3}\tMonEnd={4}\tTuesStart={5}\tTuesEnd={6}\tWedStart={7}\tWedEnd={8}\tThursStart={9}\tThursEnd={10}\tFriStart={11}\tFriEnd={12}\tSatStart={13}\tSatEnd={14}";
    public const COMMAND_UPDATE_ACC_HOLIDAY = "DATA UPDATE AccHoliday UID={0}\tHolidayName={1}\tStartDate={2}\tEndDate={3}\tTimeZone={4}";
    public const COMMAND_UPDATE_ACC_UNLOCK_COMB = "DATA UPDATE AccUnLockComb UID={0}\tGroup1={1}\tGroup2={2}\tGroup3={3}\tGroup4={4}\tGroup5={5}";
    public const COMMAND_UPDATE_BLACKLIST = "DATA UPDATE Blacklist IDNum={0}";

    // Delete
    public const COMMAND_DELETE_USER = "DATA DELETE USERINFO PIN={0}";
    public const COMMAND_DELETE_FINGER_TMP1 = "DATA DELETE FINGERTMP PIN={0}";
    public const COMMAND_DELETE_FINGER_TMP2 = "DATA DELETE FINGERTMP PIN={0}\tFID={1}";
    public const COMMAND_DELETE_FACE = "DATA DELETE FACE PIN={0}";
    public const COMMAND_DELETE_FVEIN1 = "DATA DELETE FVEIN Pin={0}";
    public const COMMAND_DELETE_FVEIN2 = "DATA DELETE FVEIN Pin={0}\tFID={1}";
    public const COMMAND_DELETE_BIO_DATA1 = "DATA DELETE BIODATA Pin={0}";
    public const COMMAND_DELETE_BIO_DATA2 = "DATA DELETE BIODATA Pin={0}\tType={1}";
    public const COMMAND_DELETE_BIO_DATA3 = "DATA DELETE BIODATA Pin={0}\tType={1}\tNo={2}";
    public const COMMAND_DELETE_USER_PIC = "DATA DELETE USERPIC PIN={0}";
    public const COMMAND_DELETE_BIO_PHOTO = "DATA DELETE BIOPHOTO PIN={0}";
    public const COMMAND_DELETE_SMS = "DATA DELETE SMS UID={0}";
    public const COMMAND_DELETE_WORK_CODE = "DATA DELETE WORKCODE CODE={0}";
    public const COMMAND_DELETE_AD_PIC = "DATA DELETE ADPIC Index={0}";

    // Query
    public const COMMAND_QUERY_ATT_LOG = "DATA QUERY ATTLOG StartTime={0}\tEndTime={1}";
    public const COMMAND_QUERY_ATT_PHOTO = "DATA QUERY ATTPHOTO StartTime={0}\tEndTime={1}";
    public const COMMAND_QUERY_USER_INFO = "DATA QUERY USERINFO PIN={0}";
    public const COMMAND_QUERY_FINGER_TMP = "DATA QUERY FINGERTMP PIN={0}\tFID={1}";
    public const COMMAND_QUERY_BIO_DATA1 = "DATA QUERY BIODATA Type={0}";
    public const COMMAND_QUERY_BIO_DATA2 = "DATA QUERY BIODATA Type={0}\tPIN={1}";
    public const COMMAND_QUERY_BIO_DATA3 = "DATA QUERY BIODATA Type={0}\tPIN={1}\tNo={2}";

    // Clear
    public const COMMAND_CLEAR_LOG = "CLEAR LOG";
    public const COMMAND_CLEAR_PHOTO = "CLEAR PHOTO";
    public const COMMAND_CLEAR_DATA = "CLEAR DATA";
    public const COMMAND_CLEAR_BIO_DATA = "CLEAR BIODATA";

    // Check
    public const COMMAND_CHECK = "CHECK";

    // Set
    public const COMMAND_SET_OPTION = "SET OPTION {0}={1}";
    public const COMMAND_SET_RELOAD_OPTIONS = "RELOAD OPTIONS";

    // File
    public const COMMAND_PUT_FILE = "PutFile {0}\t{1}";

    // Enroll
    public const COMMAND_ENROLL_FP = "ENROLL_FP PIN={0}\tFID={1}\tRETRY={2}\tOVERWRITE={3}";

    // Other
    public const COMMAND_UNKNOWN = "UNKNOWN";
}

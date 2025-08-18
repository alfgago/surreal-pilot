#pragma once

#include "CoreMinimal.h"
#include "Engine/Blueprint.h"
#include "BlueprintGraph/Classes/K2Node.h"
#include "EditorSubsystem.h"
#include "Dom/JsonObject.h"
#include "ScopedTransaction.h"

/**
 * Interface for patch application functionality
 */
class SURREALPILOT_API IPatchApplier
{
public:
    virtual ~IPatchApplier() = default;
    
    /**
     * Apply JSON patch to Blueprint or other UE objects
     * @param PatchJson JSON string containing patch operations
     * @return True if patch was applied successfully
     */
    virtual bool ApplyJsonPatch(const FString& PatchJson) = 0;
    
    /**
     * Validate if a patch can be applied without actually applying it
     * @param PatchJson JSON string containing patch operations
     * @return True if patch can be applied
     */
    virtual bool CanApplyPatch(const FString& PatchJson) = 0;
    
    /**
     * Get the last error message from patch application
     * @return Error message string
     */
    virtual FString GetLastError() const = 0;
};

/**
 * Concrete implementation of patch application functionality
 */
UCLASS()
class SURREALPILOT_API UPatchApplier : public UEditorSubsystem, public IPatchApplier
{
    GENERATED_BODY()

public:
    // USubsystem interface
    virtual void Initialize(FSubsystemCollectionBase& Collection) override;
    virtual void Deinitialize() override;

    // IPatchApplier interface
    virtual bool ApplyJsonPatch(const FString& PatchJson) override;
    virtual bool CanApplyPatch(const FString& PatchJson) override;
    virtual FString GetLastError() const override;

    /**
     * Get the singleton instance of the patch applier
     */
    UFUNCTION(BlueprintCallable, Category = "SurrealPilot")
    static UPatchApplier* Get();

private:
    /** Last error message */
    FString LastErrorMessage;
    
    /** Current transaction for undo support */
    TUniquePtr<FScopedTransaction> CurrentTransaction;

    /**
     * Parse JSON patch string into operations
     * @param PatchJson JSON string to parse
     * @return Array of patch operations
     */
    TArray<TSharedPtr<FJsonObject>> ParsePatchOperations(const FString& PatchJson);
    
    /**
     * Apply a single patch operation
     * @param Operation JSON object containing the operation
     * @return True if operation was applied successfully
     */
    bool ApplyPatchOperation(TSharedPtr<FJsonObject> Operation);
    
    /**
     * Validate a single patch operation
     * @param Operation JSON object containing the operation
     * @return True if operation is valid
     */
    bool ValidatePatchOperation(TSharedPtr<FJsonObject> Operation);
    
    /**
     * Apply variable rename operation
     * @param Operation JSON object containing rename details
     * @return True if rename was successful
     */
    bool ApplyVariableRename(TSharedPtr<FJsonObject> Operation);
    
    /**
     * Apply node addition operation
     * @param Operation JSON object containing node details
     * @return True if node was added successfully
     */
    bool ApplyNodeAddition(TSharedPtr<FJsonObject> Operation);
    
    /**
     * Apply node modification operation
     * @param Operation JSON object containing modification details
     * @return True if node was modified successfully
     */
    bool ApplyNodeModification(TSharedPtr<FJsonObject> Operation);
    
    /**
     * Apply node deletion operation
     * @param Operation JSON object containing deletion details
     * @return True if node was deleted successfully
     */
    bool ApplyNodeDeletion(TSharedPtr<FJsonObject> Operation);
    
    /**
     * Apply connection operation (connect/disconnect pins)
     * @param Operation JSON object containing connection details
     * @return True if connection was applied successfully
     */
    bool ApplyConnectionOperation(TSharedPtr<FJsonObject> Operation);
    
    /**
     * Find Blueprint by name or path
     * @param BlueprintPath Path or name of the blueprint
     * @return Blueprint object if found
     */
    UBlueprint* FindBlueprint(const FString& BlueprintPath);
    
    /**
     * Find node in blueprint by ID or name
     * @param Blueprint Blueprint to search in
     * @param NodeIdentifier Node ID or name
     * @return Node if found
     */
    UK2Node* FindNode(UBlueprint* Blueprint, const FString& NodeIdentifier);
    
    /**
     * Find variable in blueprint by name
     * @param Blueprint Blueprint to search in
     * @param VariableName Name of the variable
     * @return Variable property if found
     */
    FBPVariableDescription* FindVariable(UBlueprint* Blueprint, const FString& VariableName);
    
    /**
     * Start a new transaction for undo support
     * @param Description Description of the transaction
     */
    void BeginTransaction(const FString& Description);
    
    /**
     * End the current transaction
     */
    void EndTransaction();
    
    /**
     * Cancel the current transaction
     */
    void CancelTransaction();
    
    /**
     * Set the last error message
     * @param ErrorMessage Error message to set
     */
    void SetLastError(const FString& ErrorMessage);
    
    /**
     * Log patch operation for debugging
     * @param Operation Operation being applied
     * @param Success Whether the operation succeeded
     */
    void LogPatchOperation(TSharedPtr<FJsonObject> Operation, bool Success);
};

/**
 * Patch operation types
 */
UENUM(BlueprintType)
enum class EPatchOperationType : uint8
{
    VariableRename,
    NodeAddition,
    NodeModification,
    NodeDeletion,
    ConnectionAdd,
    ConnectionRemove,
    PropertyChange
};

/**
 * Structure for patch operation data
 */
USTRUCT(BlueprintType)
struct SURREALPILOT_API FPatchOperation
{
    GENERATED_BODY()

    /** Type of operation */
    UPROPERTY(BlueprintReadWrite, Category = "Patch")
    EPatchOperationType OperationType;
    
    /** Target blueprint path */
    UPROPERTY(BlueprintReadWrite, Category = "Patch")
    FString BlueprintPath;
    
    /** Target object identifier (node ID, variable name, etc.) */
    UPROPERTY(BlueprintReadWrite, Category = "Patch")
    FString TargetIdentifier;
    
    /** Operation parameters as JSON */
    UPROPERTY(BlueprintReadWrite, Category = "Patch")
    FString Parameters;
    
    /** Description of the operation */
    UPROPERTY(BlueprintReadWrite, Category = "Patch")
    FString Description;

    FPatchOperation()
        : OperationType(EPatchOperationType::PropertyChange)
    {
    }
};
#pragma once

#include "CoreMinimal.h"
#include "Engine/Blueprint.h"
#include "BlueprintGraph/Classes/K2Node.h"
#include "EditorSubsystem.h"
#include "Dom/JsonObject.h"

/**
 * Interface for context export functionality
 */
class SURREALPILOT_API IContextExporter
{
public:
    virtual ~IContextExporter() = default;
    
    /**
     * Export Blueprint context as JSON string
     * @param Blueprint The blueprint to export context from
     * @return JSON string containing blueprint context
     */
    virtual FString ExportBlueprintContext(UBlueprint* Blueprint) = 0;
    
    /**
     * Export build error context as JSON string
     * @param Errors Array of error messages to format
     * @return JSON string containing formatted error context
     */
    virtual FString ExportErrorContext(const TArray<FString>& Errors) = 0;
    
    /**
     * Export current selection context as JSON string
     * @return JSON string containing selection context
     */
    virtual FString ExportSelectionContext() = 0;
};

/**
 * Concrete implementation of context export functionality
 */
UCLASS()
class SURREALPILOT_API UContextExporter : public UEditorSubsystem, public IContextExporter
{
    GENERATED_BODY()

public:
    // USubsystem interface
    virtual void Initialize(FSubsystemCollectionBase& Collection) override;
    virtual void Deinitialize() override;

    // IContextExporter interface
    virtual FString ExportBlueprintContext(UBlueprint* Blueprint) override;
    virtual FString ExportErrorContext(const TArray<FString>& Errors) override;
    virtual FString ExportSelectionContext() override;

    /**
     * Get the singleton instance of the context exporter
     */
    UFUNCTION(BlueprintCallable, Category = "SurrealPilot")
    static UContextExporter* Get();

private:
    /**
     * Export blueprint graph nodes to JSON
     * @param Graph The blueprint graph to export
     * @return JSON object containing graph data
     */
    TSharedPtr<FJsonObject> ExportBlueprintGraph(UEdGraph* Graph);
    
    /**
     * Export a single node to JSON
     * @param Node The node to export
     * @return JSON object containing node data
     */
    TSharedPtr<FJsonObject> ExportNode(UK2Node* Node);
    
    /**
     * Export node pins to JSON
     * @param Node The node whose pins to export
     * @return JSON array containing pin data
     */
    TArray<TSharedPtr<FJsonValue>> ExportNodePins(UK2Node* Node);
    
    /**
     * Export blueprint variables to JSON
     * @param Blueprint The blueprint whose variables to export
     * @return JSON array containing variable data
     */
    TArray<TSharedPtr<FJsonValue>> ExportBlueprintVariables(UBlueprint* Blueprint);
    
    /**
     * Export blueprint functions to JSON
     * @param Blueprint The blueprint whose functions to export
     * @return JSON array containing function data
     */
    TArray<TSharedPtr<FJsonValue>> ExportBlueprintFunctions(UBlueprint* Blueprint);
    
    /**
     * Get currently selected objects in the editor
     * @return Array of selected objects
     */
    TArray<UObject*> GetSelectedObjects();
    
    /**
     * Export C++ class context
     * @param ClassName Name of the C++ class to export
     * @return JSON string containing C++ class context
     */
    FString ExportCppClassContext(const FString& ClassName);
    
    /**
     * Export scene/level context
     * @return JSON string containing scene context
     */
    FString ExportSceneContext();
    
    /**
     * Export actor context
     * @param Actor The actor to export context from
     * @return JSON string containing actor context
     */
    FString ExportActorContext(AActor* Actor);
    
    /**
     * Export component context
     * @param Component The component to export context from
     * @return JSON string containing component context
     */
    FString ExportComponentContext(UActorComponent* Component);
    
    /**
     * Export material context
     * @param Material The material to export context from
     * @return JSON string containing material context
     */
    FString ExportMaterialContext(UMaterialInterface* Material);
    
    /**
     * Export animation context
     * @param AnimationAsset The animation asset to export context from
     * @return JSON string containing animation context
     */
    FString ExportAnimationContext(UAnimationAsset* AnimationAsset);
    
    /**
     * Convert JSON object to formatted string
     * @param JsonObject The JSON object to convert
     * @return Formatted JSON string
     */
    FString JsonObjectToString(TSharedPtr<FJsonObject> JsonObject);
};